<?php
namespace MatthiasWeb\RealMediaLibrary\general;
use MatthiasWeb\RealMediaLibrary\base;
use MatthiasWeb\RealMediaLibrary\menu;
use MatthiasWeb\RealMediaLibrary\rest;
use MatthiasWeb\RealMediaLibrary\attachment;
use MatthiasWeb\RealMediaLibrary\comp;
use MatthiasWeb\RealMediaLibrary\order;
use MatthiasWeb\RealMediaLibrary\metadata;
use MatthiasWeb\RealMediaLibrary\usersettings;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); // Avoid direct file request

// Include files, where autoloading is not possible, yet
require_once(RML_INC . 'base/Core.class.php');
require_once(RML_INC . 'general/Exceptions.collection.php');

/**
 * Singleton core class which handles the main system for plugin. It includes
 * registering of the autoloader, all hooks (actions & filters) (see base\Core class).
 */
class Core extends base\Core {
    
    /**
     * Singleton instance.
     */
    private static $me;
    
    /**
     * The main service.
     * 
     * @see rest\Service
     */
    private $service;
    
    /**
     * @see https://github.com/Capevace/wordpress-plugin-updater
     */
    private $updater;
    
    /**
     * Application core constructor.
     */
    protected function __construct() {
        parent::__construct();
        
        // Load no-namespace API functions
        foreach (array('attachment', 'folders', 'meta') as $apiInclude) {
            require_once(RML_PATH . '/inc/api/' . $apiInclude . '.php');
        }
        
        // Register creatables
        $this->getUpdater(); // Initially load the updater
        wp_rml_register_creatable(RML_NS . '\\folder\\Folder', RML_TYPE_FOLDER);
        wp_rml_register_creatable(RML_NS . '\\folder\\Collection', RML_TYPE_COLLECTION);
        wp_rml_register_creatable(RML_NS . '\\folder\\Gallery', RML_TYPE_GALLERY);
        
        // Register all your before init hooks
        add_action('plugins_loaded', array($this, 'updateDbCheck'));
        add_action('admin_init', array(Options::getInstance(), 'register_fields'));
        add_action('RML/Migration', array(Migration::getInstance(), 'migration'), 10, 2);
        add_action('plugins_loaded', array(Migration::getInstance(), 'plugins_loaded'));
        add_action('init', array($this, 'init'));
        
        add_filter("override_load_textdomain", array(RML_NS . '\\general\\Lang', 'override_load_textdomain'), 10, 3);
        add_filter('RML/Validate/Insert', array(attachment\Permissions::getInstance(), 'insert'), 10, 3);
        add_filter('RML/Validate/Create', array(attachment\Permissions::getInstance(), 'create'), 10, 4);
        add_filter('RML/Validate/Rename', array(attachment\Permissions::getInstance(), 'setName'), 10, 3);
        add_filter('RML/Validate/Delete', array(attachment\Permissions::getInstance(), 'deleteFolder'), 10, 3);
        add_filter('wp_die_ajax_handler', array($this, 'update_count'), 1);
        add_filter('wp_die_handler', array($this, 'update_count'), 1);
        add_filter('rest_post_dispatch', array($this, 'update_count'));
        add_filter('wp_die_xmlrpc_handler', array($this, 'update_count'), 1);
        add_filter('wp_redirect', array($this, 'update_count'), 1);
        
        $this->compatibilities(false);
    }
    
    /**
     * Allow a better compatibility for other plugins.
     * 
     * Have a look at the class' constructors for all needed filters and actions. 
     * 
     * Here are also some conditions which causes that some resource files (.php)
     * are not loaded via autoloader.
     */
    private function compatibilities($init) {
        if ($init) {
            // @see https://wordpress.org/plugins/export-media-library/
            if (defined('MASSEDGE_WORDPRESS_PLUGIN_EXPORT_MEDIA_LIBRARY_PLUGIN_PATH')) {
                $data = get_plugin_data(MASSEDGE_WORDPRESS_PLUGIN_EXPORT_MEDIA_LIBRARY_PLUGIN_PATH, true, false);
                if (version_compare($data['Version'], '2.0.0', '>=')) {
                    new comp\ExportMediaLibrary();
                }
            }
        }else{
            add_action('init', array(comp\PolyLang::getInstance(), 'init'));
            add_action('init', array(comp\WPML::getInstance(), 'init'), 9);
            add_action('init', array(comp\PageBuilders::getInstance(), 'init'));
        }
    }
    
    /**
     * The init function is fired even the init hook of WordPress. If possible
     * it should register all hooks to have them in one place.
     */
    public function init() {
        // Add our folder shortcode
        global $shortcode_tags;
        add_shortcode('folder-gallery', $shortcode_tags['gallery']);
        FolderShortcode::getInstance();
        
        $this->service = new rest\Service();
        
        // Register all your hooks here
        add_action('rest_api_init', array($this->getService(), 'rest_api_init'));
        add_action('rest_api_init', array(new rest\Folder(), 'rest_api_init'));
        add_action('rest_api_init', array(new rest\Attachment(), 'rest_api_init'));
        add_action('rest_api_init', array(new rest\Reset(), 'rest_api_init'));
        add_action('rest_attachment_collection_params', array(new rest\Attachment(), 'rest_attachment_collection_params'));
        add_action('rest_attachment_query', array(new rest\Attachment(), 'rest_attachment_query'), 10, 2);
        add_action('admin_enqueue_scripts', array($this->getAssets(), 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this->getAssets(), 'wp_enqueue_scripts'));
        add_action('pre_get_posts', array(attachment\Filter::getInstance(), 'pre_get_posts'), 998);
        add_action('delete_attachment', array(attachment\Shortcut::getInstance(), 'delete_attachment'));
        add_action('delete_attachment', array(attachment\Filter::getInstance(), 'delete_attachment'));
        add_action('plugin_row_meta', array($this->getAssets(), 'plugin_row_meta'), 10, 2 );
        add_action('pre-upload-ui', array(attachment\Upload::getInstance(), 'pre_upload_ui'));
        add_action('add_attachment', array(attachment\Upload::getInstance(), 'add_attachment'));
        add_action('wp_prepare_attachment_for_js', array(attachment\Filter::getInstance(), 'wp_prepare_attachment_for_js'), 10, 3);
        add_action('RML/Item/MoveFinished', array(RML_NS . '\\order\\Sortable', 'item_move_finished'), 1, 4);
        add_action('RML/Options/Register', array(comp\ExImport::getInstance(), 'options_register'));
        add_action('RML/Folder/Deleted', array(metadata\Meta::getInstance(), 'folder_deleted'), 10, 2);
        add_action("deleted_realmedialibrary_meta", array(RML_NS . '\\folder\\Creatable', 'deleted_realmedialibrary_meta'), 10, 3);

        add_filter('posts_clauses', array(attachment\Filter::getInstance(), 'posts_clauses'), 10, 2);
        add_filter('media_view_strings', array($this->getAssets(), 'media_view_strings'));
        add_filter('media_row_actions', array($this->getAssets(), 'media_row_actions'), 10, 2);
        add_filter('add_post_metadata', array(attachment\Shortcut::getInstance(), 'add_post_metadata'), 999999, 5);
        add_filter('update_post_metadata', array(attachment\Shortcut::getInstance(), 'update_post_metadata'), 999999, 5);
        add_filter('get_post_metadata', array(attachment\Shortcut::getInstance(), 'get_post_metadata'), 999999, 4);
        add_filter('attachment_fields_to_edit', array(attachment\CustomField::getInstance(), 'attachment_fields_to_edit'), 10, 2);
        add_filter('attachment_fields_to_save', array(attachment\CustomField::getInstance(), 'attachment_fields_to_save'), 10 , 2);
        add_filter('restrict_manage_posts', array(attachment\Filter::getInstance(), 'restrict_manage_posts'));
        add_filter('ajax_query_attachments_args', array(attachment\Filter::getInstance(), 'ajax_query_attachments_args'));
        add_filter('mla_media_modal_query_final_terms', array(attachment\Filter::getInstance(), 'ajax_query_attachments_args'));
        add_filter('shortcode_atts_gallery', array(FolderShortcode::getInstance(), 'shortcode_atts_gallery'), 1, 3 );
        add_filter('posts_clauses', array(RML_NS . '\\order\\Sortable', 'posts_clauses'), 10, 2);
        add_filter('mla_media_modal_query_final_terms', array(RML_NS . '\\order\\Sortable', 'mla_media_modal_query_final_terms'), 10, 2);
        add_filter('superpwa_sw_never_cache_urls', array($this->getService(), 'superpwa_exclude_from_cache'));

        add_rml_meta_box('general', metadata\Meta::getInstance(), false, 0 );
        add_rml_meta_box('description', new metadata\Description(), false, 0 );
        add_rml_meta_box('coverImage', new metadata\CoverImage(), false, 0 );
        add_rml_user_settings_box('allFilesShortcuts', new usersettings\AllFilesShortcuts(), false, 0);
        //add_rml_user_settings_box('demo', new \MatthiasWeb\RealMediaLibrary\usersettings\Demo(), false, 0);
        
        // Gutenberg blocks
        if (function_exists('register_block_type')) {
            Gutenberg::getInstance();
            add_action('enqueue_block_editor_assets', array($this->getAssets(), 'enqueue_block_editor_assets'));
        }
        
        // Show plugin notice
        if (!$this->getUpdater()->isActivated()) {
            add_action('after_plugin_row_' . plugin_basename(RML_FILE), array($this, 'after_plugin_row'), 10, 2 );
        }
        
        $this->compatibilities(true);
        
        //add_action("RML/Tree/Parsed", function($parsed) {
        //    foreach ($parsed as $folder) {
        //        if ($folder->getId() === 29) {
        //            $folder->setVisible(false);
        //        }
        //    }
        //    return $parsed;
        //});
    }
    
    /**
     * Get the service.
     * 
     * @return rest\Service
     */
    public function getService() {
        return $this->service;
    }
    
    /**
     * Use the wp die filter to make the last update count;
     */
    public function update_count($result) {
        attachment\CountCache::getInstance()->wp_die();
        /**
         * This function is called at the end of: AJAX Handler, WP Handler, REST Handler.
         * You can collect for example batch actions and merge it to one SQL query.
         * 
         * @hook RML/Die
         * @since 4.0.2
         */
        do_action('RML/Die');
        return $result;
    }
    
    /**
     * Get the updater instance.
     * 
     * @see https://github.com/matzeeable/wordpress-plugin-updater
     */
    public function getUpdater() {
        if ($this->updater === null) {
            $this->updater = \MatthiasWeb\WPU\V4\WPLSController::initClient('https://license.matthias-web.com/', array(
                'name'      => 'WP Real Media Library',
                'version'   => RML_VERSION,
                'path'      => RML_FILE,
                'slug'      => 'real-media-library',
                'newsletterPrivacy' => 'https://matthias-web.com/privacypolicy/'
            ));
            add_action('wpls_email_real-media-library', array($this, 'handleNewsletter'));
        }
        return $this->updater;
    }
    
    /**
     * Set and/or get the value if the license notice is dismissed.
     * 
     * @param boolean $set
     * @return boolean
     */
    public function isLicenseNoticeDismissed($set = null) {
        $transient = RML_OPT_PREFIX . '_licenseActivated';
        $value = '1';
        
        if ($set !== null) {
            if ($set) {
                set_site_transient($transient, $value, 365 * DAY_IN_SECONDS);
            }else{
                delete_site_transient($transient);
            }
        }
        
        return get_site_transient($transient) === $value;
    }
    
    /**
     * Show a notice in the plugins list that the plugin is not activated, yet.
     */
    public function after_plugin_row($file, $plugin) {
        $wp_list_table = _get_list_table('WP_Plugins_List_Table');
		printf(
			'<tr class="rml-update-notice active">
	<th colspan="%d" class="check-column">
    	<div class="plugin-update update-message notice inline notice-warning notice-alt">
        	<div class="update-message">%s</div>
    	</div>
    </th>
</tr>',
			$wp_list_table->get_column_count(),
			wpautop(__('<strong>You have not yet entered the license key</strong>. To receive automatic updates, please enter the key in "Enter license".', RML_TD))
		);
    }
    
    /**
     * Send an email newsletter if checked in the updater.
     */
    public function handleNewsletter($email) {
        wp_remote_get(
            'https://matthias-web.com/wp-json/matthiasweb/v1/newsletter/subscribe?email=' .
                urlencode($email) .
                '&referer=' .
                urlencode(home_url())
        );
    }
    
    /**
     * Get singleton core class.
     * 
     * @return Core
     */
    public static function getInstance() {
        return !isset(self::$me) ? self::$me = new Core() : self::$me;
    }
    
    /**
     * Static method to get a RML table name.
     * 
     * @see Core::getTableName
     */
    public static function tableName($name = '') {
        return self::getInstance()->getTableName($name);
    }
}