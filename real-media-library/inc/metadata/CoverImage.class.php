<?php
namespace MatthiasWeb\RealMediaLibrary\metadata;
use MatthiasWeb\RealMediaLibrary\general;
use MatthiasWeb\RealMediaLibrary\api;
use MatthiasWeb\RealMediaLibrary\base;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Implements a cover image for root folder, collections, galleries and normal folders.
 */
class CoverImage extends base\Base implements api\IMetadata {
    public function __construct() {
        add_action("delete_attachment", array($this, "delete_attachment"));
        add_action('wp_ajax_get-attachment-by-url', array($this, 'ajax_get_attachment_by_url'), 15);
    }
    
    /**
     * @see https://www.npmjs.com/package/wp-media-picker#implement-additional-ajax-function
     */
    public function ajax_get_attachment_by_url() {
        if ( ! isset( $_REQUEST['url'] ) ) {
            wp_send_json_error();
        }
        
        $id = attachment_url_to_postid( $_REQUEST['url'] );
        if ( ! $id ) {
            wp_send_json_error();
        }
        
        $_REQUEST['id'] = $id;
        wp_ajax_get_attachment();
        die();
    }
    
    public function delete_attachment($postid) {
        delete_metadata('realmedialibrary', null, "coverImage", $postid, true);
    }
    
    public function scripts($assets) {
        $assets->enqueueLibraryScript('wp-media-picker', 'wp-media-picker/wp-media-picker.min.js');
        $assets->enqueueLibraryStyle('wp-media-picker', 'wp-media-picker/wp-media-picker.min.css');
    }
    
    public function content($content, $folder) {
        $id = $this->getAttachmentID($folder->getId());
        return $content . '<label>' . __('Cover image', RML_TD) . '</label><input name="coverImage" data-wprfc-visible="1" data-wprfc="metaCoverImage" value="' . esc_attr($id) . '" type="text" />';
    }
    
    public function save($response, $folder, $request) {
        $fid = $folder->getId();
        $coverImage = $this->getAttachmentID($fid);
        $new = (int) $request->get_param('coverImage');
        
        if ($coverImage !== $new) {
            if (wp_attachment_is_image($new)) {
                update_media_folder_meta($fid, "coverImage", $new);
            }else{
                // Delete it
                delete_media_folder_meta($fid, "coverImage");
            }
        }
        return $response;
    }
    
    public function getAttachmentID($fid) {
        return (int) get_media_folder_meta($fid, "coverImage", true);
    }
}