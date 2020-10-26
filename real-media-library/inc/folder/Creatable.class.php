<?php
namespace MatthiasWeb\RealMediaLibrary\folder;
use MatthiasWeb\RealMediaLibrary\attachment;
use MatthiasWeb\RealMediaLibrary\general;
use MatthiasWeb\RealMediaLibrary\api;
use MatthiasWeb\RealMediaLibrary\order;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Abstract class for a creatable folder item. It handles all general
 * actions for a folder item. If you want to add an new folder type, have a
 * look at the api function wp_rml_register_creatable();
 * 
 * A new folder type MUST have the implementation with class FOLDERTYPE
 * extends order\Sortable because every folder can also be sortable!
 */
abstract class Creatable extends BaseFolder {
    
    static $cachedOrders = null;
    
    /**
     * C'tor with the main properties.
     * 
     * The constructor does not throw any errors because when it is fully filled with parameters
     * it expects the right properties from the database.
     * 
     * Only ::instance and ::create should create instances from this class!
     * 
     * Synced with order\Sortable::__construct
     */
    public function __construct($id, $parent = -1, $name = "", $slug = "", $absolute = "", $order = -1, $cnt = 0, $row = array()) {
        // Check, if the folder type is defined in the right way
        if (!$this instanceof order\Sortable) {
            $className = explode("\\", get_class($this));
            $className = $className[count($className) - 1];
            throw new \Exception("The folder type is defined in the wrong way! Please use the class definition:\n
                use " . RML_NS . "\\order; // use namespace
                class $className extends order\Sortable { ... }\n\n... You can disable the sortable functionality by set the contentCustomOrder to 2 in the database.");
        }
        
        // Set properties
        $this->id = $id;
        $this->parent = $parent;
        $this->name = $name;
        $this->cnt = $cnt >= 0 ? $cnt : 0;
        $this->order = $order;
        $this->children = array();
        $this->slug = $slug;
        $this->absolutePath = $absolute;
        $this->owner = isset($row->owner) ? $row->owner : get_current_user_id();
        $this->row = $row;

        // Parse the restrictions
        if (isset($row->restrictions) && is_string($row->restrictions) && strlen($row->restrictions) > 0) {
            $this->restrictions = explode(',', $row->restrictions);
            $this->restrictionsCount = count($this->restrictions);
        }
    }
    
    // documentated in IFolderActions
    public function read($order = null, $orderby = null) {
        return self::xread($this->id, $order, $orderby);
    }
    
    // documentated in IFolderActions
    public function relocate($parentId, $nextFolderId = false) {
        global $wpdb;
        
        // Collect data
        $table_name = $this->getTableName();
        $this->debug($parentId === $this->id ? "Start to relocate folder $this->id inside parent..." : "Start to relocate folder $this->id to parent $parentId...", __METHOD__);
        $this->debug($nextFolderId === false ? "The folder should take place at the end of the list..." : "The folder should take place before folder id $nextFolderId...", __METHOD__);
        $parent = $parentId === $this->id ? $this : wp_rml_get_object_by_id($parentId);
        $next = $nextFolderId === false ? null : wp_rml_get_object_by_id($nextFolderId);
        
        // At end of the list
        try {
            if ($next === null && is_rml_folder($parent)) {
                // Only set the parent
                $this->setParent($parent->id);
            }else if (is_rml_folder($next) && is_rml_folder($parent)) {
                // Reindex and reget
                $parent->reindexChildrens();
                $_this = wp_rml_structure_reset(null, false, $this->id);
                $next = wp_rml_get_object_by_id($next->id);
                
                // Get the order of the next folder
                $newOrder = $next->order;
                
                // Count up the next ids
                $sql = "UPDATE $table_name SET ord = ord + 1 WHERE parent = $parent->id AND ord >= $newOrder";
                $wpdb->query($sql);
                
                // Set the new parent
                $_this->setParent($parent->id, $newOrder);
            }else{
                // There is nothing given
                throw new \Exception(__("Something went wrong.", RML_TD));
            }
            
            $this->debug("Successfully relocated", __METHOD__);
            return true;
        }catch (\Exception $e) {
            $this->debug("Error: " . $e->getMessage(), __METHOD__);
            return array($e->getMessage());
        }
    }
    
    // documentated in IFolderActions
    public function orderSubfolders($orderby, $writeMetadata = true) {
        $orders = self::getAvailableSubfolderOrders();
        $core = general\Core::getInstance();
        $core->debug("Try to order the subfolders of $this->id by $orderby...", __METHOD__);
        if (in_array($orderby, array_keys($orders))) {
            global $wpdb;
            
            // Get order
            $split = explode("_", $orderby);
            $order = $orders[$orderby];
            $direction = $split[1];
            $table_name = general\Core::getInstance()->getTableName();
            
            // Run SQL
            $sql = $wpdb->prepare("UPDATE $table_name AS rmlo2
                LEFT JOIN (
                	SELECT @rownum := @rownum + 1 AS ord, t.id
                	FROM ( SELECT rmlo.id
                		FROM $table_name AS rmlo
                		WHERE rmlo.parent = %d
                		ORDER BY " . $order["sqlOrder"] . " $direction ) AS t, (SELECT @rownum := 0) AS r
                ) AS rmlonew ON rmlo2.id = rmlonew.id
                SET rmlo2.ord = rmlonew.ord
                WHERE rmlo2.parent = %d", $this->id, $this->id);
            $wpdb->query($sql);
            
            // Save in the metadata
            if ($writeMetadata) {
                update_media_folder_meta($this->id, "lastSubOrderBy", $orderby);
            }
            $core->debug("Successfully ordered folder", __METHOD__);
            return true;
        }else{
            $core->debug("'$orderby' is not a valid order...", __METHOD__);
            return false;
        }
    }
    
    // documentated in IFolderActions
    public function resetSubfolderOrder() {
        delete_media_folder_meta($this->id, "lastSubOrderBy");
        $this->debug("Deleted subfolder order of the folder $this->id", __METHOD__);
        return true;
    }
    
    /**
     * Check if the current folders parent is automatically ordered by a criterium so
     * the order can be applied. This should be called when the hierarchy of the
     * folder is changed or when a new folder is added to that parent.
     */
    protected function applySubfolderOrderBy() {
        $parent = wp_rml_get_object_by_id($this->getParent());
        if (!is_rml_folder($parent)) {
            return false;
        }
        
        $orderAutomatically = (boolean) $parent->getRowData('subOrderAutomatically');
        
        if ($orderAutomatically) {
            $order = $parent->getRowData('lastSubOrderBy');
            
            if (!empty($order)) {
                $this->debug("New subfolder " . $this->getId() . " in folder " . $parent->getId() . ", automatically order by $order ...", __METHOD__);
                return $parent->orderSubfolders($order, false);
            }
        }
        return false;
    }
    
    // Documentated in IFolderActions
    public function reindexChildrens($resetData = false) {
        global $wpdb;
        $table_name = $this->getTableName();
        $sql = "UPDATE $table_name AS rml2
                LEFT JOIN (
                	SELECT @rownum := @rownum + 1 AS nr, t.ID
                    FROM ( SELECT rml.id
                        FROM $table_name AS rml
                        WHERE rml.parent = $this->id
                        ORDER BY rml.ord )
                        AS t, (SELECT @rownum := 0) AS r
                ) AS rmlnew ON rml2.id = rmlnew.id
                SET rml2.ord = rmlnew.nr
                WHERE rml2.parent = $this->id";
        
        $wpdb->query($sql);
        $this->debug("Reindexed the childrens order of $this->id", __METHOD__);
        if ($resetData) {
            wp_rml_structure_reset(null, false);
        }
        return true;
    }
    
    // Documentated in IFolderActions
    public function insert($ids, $supress_validation = false, $isShortcut = false) {
        $this->debug("Start moving files " . json_encode($ids) . " to $this->id...", __METHOD__);
        if (is_array($ids)) {
            // Reset last shortcut ids
            if ($isShortcut) {
                attachment\Shortcut::getInstance()->_resetLastIds();
            }
            
            // Create posts cache to avoid multiple SQL queries in _wp_rml_synchronize_attachment
            $cacheIds = array();
            foreach ($ids as $value) {
                if (!wp_cache_get($value, "posts")) {
                    $cacheIds[] = $value;
                }
            }
            if (count($cacheIds) > 0) {
                $this->debug("Get and cache the following post ids: " . implode(",", $cacheIds), __METHOD__);
                get_posts(array(
                    "numberposts" => -1,
                    "include" => $cacheIds
                ));
            }
            
            // Iterate all items
            foreach ($ids as $value) {
                $this->singleCheckInsert($value);
                
                // Check if other fails are counted
                if ($supress_validation === false) {
                    $this->singleCheckInsertPermissions($value);
                }
            }
            
            /**
             * This action is fired before items gets moved to a specific folder.
             * It allows you for example to throw an exception with an error message
             * to cancel the movement.
             * 
             * @param {int} $fid The destination folder id
             * @param {int[]} $attachments The attachment post ids
             * @param {IFolder} $folder The folder object
             * @param {boolean} $isShortcut If true the attachments are copied to a folder
             * @hook RML/Item/Move
             */
            do_action("RML/Item/Move", $this->id, $ids, $this, $isShortcut);
            
            // Get the folder IDs of the attachments
            $foldersToUpdate = wp_attachment_folder($ids);
            $sourceFolders = $foldersToUpdate;
            
            // Update the folder
            foreach ($ids as $value) {
                _wp_rml_synchronize_attachment($value, $this->id, $isShortcut);
            }
            
            // Update the count and shortcuts
            $foldersToUpdate[] = $this->id;
            wp_rml_update_count($foldersToUpdate);
            
            // Finish
            $this->debug("Successfully moved (isShortcut: $isShortcut)", __METHOD__);
            
            /**
             * This action is fired after items gets moved to a specific folder.
             * 
             * @param {int} $fid The destination folder id
             * @param {int[]} $attachments The attachment post ids
             * @param {IFolder} $folder The folder object
             * @param {boolean} $isShortcut If true the attachments are copied to a folder
             * @param {int[]} $sourceFolders The´source folder ids of the attachments (since 4.0.9)
             * @hook RML/Item/MoveFinished
             */
            do_action("RML/Item/MoveFinished", $this->id, $ids, $this, $isShortcut, $sourceFolders);
            
            general\Util::getInstance()->doActionAnyParentHas($this, "Folder/Insert", array($this->id, $ids, $this, $isShortcut, $sourceFolders));
            return true;
        }else{
            throw new \Exception(__("You need to provide a set of files.", RML_TD));
        }
    }
    
    /**
     * Simply check, if an id can be inserted in this folder. If something is
     * wrong with the id, please throw an exception!
     * 
     * @param int $id The id
     * @throws Exception
     */
    protected function singleCheckInsertPermissions($id) {
        /**
         * Checks if an attachment can be inserted into a folder.
         * 
         * @param {string[]} $errors An array of errors
         * @param {int} $id The folder id
         * @param {IFolder} $folder The folder object
         * @hook RML/Validate/Insert
         * @return {string[]} When the array has one or more items the movement is cancelled with the string message
         */
        $validation = apply_filters("RML/Validate/Insert", array(), $id, $this);
        if (count($validation) > 0) {
            throw new \Exception(implode(" ", $validation));
        }
    }
    
    /**
     * Simply check, if an id can be inserted in this folder. If something is
     * wrong with the id, please throw an exception!
     * 
     * @param int $id The id
     * @throws Exception
     */
    protected function singleCheckInsert($id) {
        // Silence is golden.
    }
    
    /**
     * Persist the given creatable with the database. Think about it, that this only
     * works, when the ID === -1 (that means, it will be a new folder).
     * 
     * After the folder is created, this instance is useless, you must get the 
     * folder with the API wp_rml_get_by_id
     * 
     * @throws Exception
     * @return integer ID of the newly created folder
     */
    public function persist() {
        $this->debug("Persist to database...", __METHOD__);
        if ($this->id === -1) {
            global $wpdb;
            
            // Check, if the parent exists
            $parentObj = wp_rml_get_object_by_id($this->parent);
            if (!is_rml_folder($parentObj)) {
                throw new \Exception(__("The parent $this->parent does not exist.", RML_TD));
            }
            
            // Create it!
        	$table_name = $this->getTableName();
        	$insert = $wpdb->insert( 
        		$table_name,
        		array( 
        			'parent' => $this->parent,
        			'slug' => $this->getSlug(),
        			'name' => $this->name,
        			'type' => $this->getType(),
        			'ord' => $this->order > -1 ? $this->order : $parentObj->getMaxOrder() + 1,
        			'restrictions' => implode(",", array_unique($this->restrictions)),
        			'owner' => $this->owner
        		)
        	);

        	if ($insert !== false) {
        	    $this->id = $wpdb->insert_id;
        	    $this->updateThisAndChildrensAbsolutePath();
        	    wp_rml_structure_reset(null, false);
        	    
        	    /**
        	     * A new folder is created.
        	     * 
        	     * @param {int} $parent The parent folder id
        	     * @param {string} $name The folder name
        	     * @param {int} $type The folder type
        	     * @param {int} $id The folder id
        	     * @hook RML/Folder/Created
        	     */
            	do_action("RML/Folder/Created", $this->parent, $this->name, $this->getType(), $this->id);
            	$this->debug("Successfully persisted creatable with id " . $this->id, __METHOD__);
            	
            	$this->applySubfolderOrderBy();
            	
            	return $this->id;
        	}else{
        	    throw new \Exception(__("The folder could not be created in the database.", RML_TD));
        	}
        }else{
            throw new \Exception(__("The folder could not be created because it already exists.", RML_TD));
        }
    }
    
    // Documentated in IFolderActions
    public function updateThisAndChildrensAbsolutePath() {
        // Update this absolute path
        $this->getAbsolutePath(true, true);
        
        // Update children
        $childs = $this->getChildren();
        if (is_array($childs) && count($childs) > 0) {
            foreach ($childs as $key => $value) {
                $value->updateThisAndChildrensAbsolutePath();
            }
        }
    }
    
    /**
     * DO NOT USE THIS FUNCTION! IT IS ONLY FOR STRUCTURE PURPOSES.
     */
    public function addChildren($children) {
        $this->children[] = $children;
    }
	
    // Documentated in IFolder
    public function getMaxOrder() {
        global $wpdb;
        $table_name = $this->getTableName();
        $order = $wpdb->get_var("SELECT MAX(ord) FROM $table_name WHERE parent=$this->id");
        return is_numeric($order) ? $order : 0;
    }
    
    // Documentated in IFolder
    public function getRowData($field = null) {
        if (is_object($this->row)) {
            if ($field == null) {
                return $this->row;
            }else{
                return $this->row->$field;
            }
        }else{
            return false;
        }
    }
    
    // Documentated in IFolder
    public function getTypeName($default = null) {
        /**
         * Filter the description name for a custom folder type.
         * 
         * @param {string} $name The name
         * @param {int} $type The type
         * @param {int} $fid The folder id
         * @return {string}
         * @hook RML/Folder/Type/Name
         */
        return apply_filters("RML/Folder/Type/Name", $default === null ? __('Folder', RML_TD) : $default, $this->getType(), $this->getId());
    }
    
    // Documentated in IFolder
    public function getTypeDescription($default = null) {
        /**
         * Filter the description for a custom folder type.
         * 
         * @param {string} $description The description
         * @param {int} $type The type
         * @param {int} $fid The folder id
         * @return {string}
         * @hook RML/Folder/Type/Name
         */
        return apply_filters("RML/Folder/Type/Description", $default === null ? __('A folder can contain every type of file or a collection, but no gallery.', RML_TD) : $default, $this->getType(), $this->getId());
    }
    
    // Documentated in IFolderActions
    public function setVisible($visible) {
        $this->visible = $visible;
    }
    
    // Documentated in IFolderActions
    public function setParent($id, $ord = -1, $force = false) {
        // Get the parent id
        $this->debug("Try to set parent of $this->id from $this->parent to $id...", __METHOD__);
        
        // Get the parent object
        $parent = wp_rml_get_object_by_id($id);
        if ($id == $this->parent) {
            $this->debug("The parent is the same, propably only the order is changed...", __METHOD__);
        }else{
            // Check if parent folder is given
            if ($parent === null) {
                throw new \Exception(__("The given parent does not exist to set the parent for this folder.", RML_TD));
            }
            
            // Check if allowed to change the parent
            if (!$force && $this->isRestrictFor("par")) {
                throw new \Exception(__("You are not allowed to change the parent for this folder.", RML_TD));
            }
            
            // Check, if the folder type is allowed here
            if (!$force && !$parent->isValidChildrenType($this->getType())) {
                throw new \Exception(__("The given parent does not allow the folder type.", RML_TD));
            }
            
            // Check, if the parent has already the given folder name
            if ($parent->hasChildren($this->name)) {
                throw new general\FolderAlreadyExistsException($id, $this->name);
            }
        }
        
        $newOrder = $ord > -1 ? $ord : $parent->getMaxOrder() + 1;
        $isRelocate = $id == $this->parent;
        
        /**
         * This action is called when a folder was relocated in the folder tree. That
         * means the parent was not changed, only the order was changed.
         * 
         * @param {IFolder} $folder The folder object
         * @param {int} $id The new parent folder id
         * @param {int} $order The (new) order number
         * @param {boolean} $force If true the relocating was forced
         * @hook RML/Folder/Relocate
         * @since 4.0.7
         */
         
        /**
         * This action is called when a folder was moved in the folder tree. That
         * means the parent and order was changed.
         * 
         * @param {IFolder} $folder The folder object
         * @param {int} $id The new parent folder id
         * @param {int} $order The (new) order number
         * @param {boolean} $force If true the relocating was forced
         * @hook RML/Folder/Move
         * @since 4.0.7
         */
        do_action($isRelocate ? 'RML/Folder/Relocate' : 'RML/Folder/Move', $this, $id, $newOrder, $force);
        
        $oldData = $this->getRowData();
        $beforeId = $this->parent;
        $this->parent = $id;
        $this->order = $newOrder;
        $this->debug("Use $this->order (passed $ord as parameter) as new order value", __METHOD__);
        
        // Save in database
        if ($this->id > -1) {
            global $wpdb;
            
            // Update childrens
            if ($beforeId != $this->parent) {
                $this->updateThisAndChildrensAbsolutePath();
            }
            
            // Update order
            $table_name = $this->getTableName();
            $wpdb->query($wpdb->prepare("UPDATE $table_name SET parent=%d, ord=%d WHERE id = %d", $id, $this->order, $this->id));
            
            // Finish
            
            /**
             * This action is called when a folder was relocated in the folder tree. That
             * means the parent was not changed, only the order was changed.
             * 
             * @param {IFolder} $folder The folder object
             * @param {int} $id The new parent folder id
             * @param {int} $order The (new) order number
             * @param {boolean} $force If true the relocating was forced
             * @param {object} $oldData The old SQL row data (raw) of the folder
             * @hook RML/Folder/Relocated
             */
             
            /**
             * This action is called when a folder was moved in the folder tree. That
             * means the parent and order was changed.
             * 
             * @param {IFolder} $folder The folder object
             * @param {int} $id The new parent folder id
             * @param {int} $order The (new) order number
             * @param {boolean} $force If true the relocating was forced
             * @param {object} $oldData The old SQL row data (raw) of the folder
             * @hook RML/Folder/Moved
             */
            do_action($isRelocate ? 'RML/Folder/Relocated' : 'RML/Folder/Moved', $this, $id, $this->order, $force, $oldData);
            $this->debug("Successfully moved and saved in database", __METHOD__);
            
            general\Util::getInstance()->doActionAnyParentHas($this, "Folder/RelocatedOrMoved", array($this, $id, $this->order, $force, $oldData));
            $this->applySubfolderOrderBy();
        }else{
            $this->debug("Successfully setted the new parent", __METHOD__);
            $this->getAbsolutePath(true, true);
        }
        
        return true;
    }
    
    // Documentated in IFolder
    public function setName($name, $supress_validation = false) {
        $this->debug("Try to set name of $this->id from '$this->name' to '$name'...", __METHOD__);

        // Check valid folder name
        if (!$this->isValidName($name)) {
            throw new \Exception(sprintf(__("'%s' is not a valid folder name.", RML_TD), $name));
        }
        
        // Check, if the parent has already the given folder name
        $parent = wp_rml_get_object_by_id($this->parent);
        if ($parent !== null && $parent->hasChildren($name)) {
            throw new general\FolderAlreadyExistsException($this->parent, $name);
        }
        
        if ($supress_validation === false) {
            /**
             * Checks if a folder can be renamed.
             * 
             * @param {string[]} $errors An array of errors
             * @param {string} $name The new folder name
             * @param {IFolder} $folder The folder object
             * @hook RML/Validate/Rename
             * @return {string[]} When the array has one or more items the rename process is cancelled with the string message
             */
            $validation = apply_filters("RML/Validate/Rename", array(), $name, $this);
            if (count($validation) > 0) {
                throw new \Exception(implode(" ", $validation));
            }
        }
        
        /**
         * This action is called before a folder gets renamed.
         * 
         * @param {string} $name The new folder name
         * @param {IFolder} $folder The folder object
         * @hook RML/Folder/Rename
         * @since 4.0.7
         */
        do_action('RML/Folder/Rename', $name, $this);
        $oldData = $this->getRowData();
        $this->name = $name;

        // Save in Database
        if ($this->id > -1) {
            global $wpdb;
            $this->updateThisAndChildrensAbsolutePath();
            $table_name = $this->getTableName();
            $wpdb->query($wpdb->prepare("UPDATE $table_name SET name=%s WHERE id = %d", $name, $this->id));
            
            /**
             * This action is called when a folder was renamed.
             * 
             * @param {string} $name The new folder name
             * @param {IFolder} $folder The folder object
             * @param {object} $oldData The old SQL row data (raw) of the folder
             * @hook RML/Folder/Renamed
             */
            do_action('RML/Folder/Renamed', $name, $this, $oldData);
            $this->debug("Successfully renamed and saved in database", __METHOD__);
        }else{
            $this->debug("Successfully setted the new name", __METHOD__);
            $this->getAbsolutePath(true, true);
        }
        return true;
    }
    
    /**
     * Checks, if a given folder name is valid. The name is also santisized so there can
     * be no problem for physical moves for example.
     * 
     * @param string $name The folder name
     * @return boolean
     */
    public function isValidName($name) {
        $name = trim($name);
        return /*strpbrk($name, "\\/?%*:|\"<>") === FALSE &&*/ strlen($name) > 0 && !in_array($name, $this->systemReservedFolders);
    }
    
    /**
     * Read ids for a given folder id.
     * 
     * @param int $id The folder id (-1 for root)
     * @param string $order The order
     * @param string $orderby The order by
     * @return array with ids
     */
    public static function xread($id, $order = null, $orderby = null) {
        $args = array(
        	'post_status' => 'inherit',
        	'post_type' => 'attachment',
        	'posts_per_page' => -1,
	        'rml_folder' => $id,
	        'fields' => 'ids'
        );
        
        // Set orders
        if ($order !== null) {
            $args["order"] = $order;
        }
        if ($orderby !== null) {
            $args["orderby"] = $orderby;
        }
        
        /**
         * Modify the query arguments to fetch attachments within a folder.
         * 
         * @param {array} $query The query with post_status, post_type and rml_folder
         * @hook RML/Folder/QueryArgs
         * @return {array} The query
         */
        $args = apply_filters('RML/Folder/QueryArgs', $args);
        $query = new \WP_Query($args);
        $posts = $query->get_posts();
        
        /**
         * The folder content (attachments) is fetched.
         * 
         * @param {int[]|WP_Post[]} $posts The posts
         * @return {int[]|WP_Post[]}
         * @hook RML/Folder/QueryResult
         */
        $posts = apply_filters('RML/Folder/QueryResult', $posts);
        return $posts;
    }
    
    /**
     * Delete the subOrderAutomatically metadata when deleting the subfolder
     * order and also reset the subfolder order. It also handles the content order.
     */
    public static function deleted_realmedialibrary_meta($meta_ids, $object_id, $meta_key) {
        global $wpdb;
        
        if (empty($object_id)) {
            return;
        }
        
        if ($meta_key === "lastSubOrderBy") {
            // The default is to order by ID ascending
            $folder = wp_rml_get_object_by_id($object_id);
            if (is_rml_folder($folder)) {
                $folder->orderSubfolders("id_asc", false);
            }
            
            $wpdb->query($wpdb->prepare("UPDATE " . general\Core::getInstance()->getTableName() . " SET ord=NULL, oldCustomOrder=NULL WHERE id=%d", $object_id));
            delete_media_folder_meta($object_id, "subOrderAutomatically");
        }
        
        if ($meta_key === "orderby") {
            $wpdb->query($wpdb->prepare("UPDATE " . general\Core::getInstance()->getTableName("posts") . " SET nr=NULL, oldCustomNr=NULL WHERE fid=%d", $object_id));
            $wpdb->query($wpdb->prepare("UPDATE " . general\Core::getInstance()->getTableName() . " SET contentCustomOrder=0 WHERE id=%d", $object_id));
            delete_media_folder_meta($object_id, "orderAutomatically");
        }
    }
    
    /**
     * Get all available order methods for subfolders.
     * 
     * @return array
     * @see this::orderSubfolders()
     */
    public static function getAvailableSubfolderOrders($asMap = false) {
        if (self::$cachedOrders === null) {
            $orders = array(
                "name_asc" => array(
                    "label" => __("Order by name ascending", RML_TD),
                    "sqlOrder" => "rmlo.name"
                ),
                "name_desc" => array(
                    "label" => __("Order by name descending", RML_TD),
                    "sqlOrder" => "rmlo.name"
                ),
                "id_asc" => array(
                    "label" => __("Order by ID ascending", RML_TD),
                    "sqlOrder" => "rmlo.id"
                ),
                "id_desc" => array(
                    "label" => __("Order by ID descending", RML_TD),
                    "sqlOrder" => "rmlo.id"
                )
            );
            /**
             * Add an available order criterium to the tree. If you pass
             * user input to the SQL Order please be sure the values are escaped!
             * 
             * @example
             * $orders["id_asc"] = array(
             *  "label" => __("Order by ID ascending", RML_TD),
             *  "sqlOrder" => "rml.ID"
             * )
             * @param {object[]} $orders The available orders
             * @return {object[]}
             * @hook RML/Tree/Orderby
             * @since 4.4.0
             */
            self::$cachedOrders = apply_filters("RML/Tree/Orderby", $orders);
        }
        
        if ($asMap) {
            $sortables = array();
            foreach (self::$cachedOrders as $key => $value) {
                $sortables[$key] = $value["label"];
            }
            return $sortables;
        }
        
        return self::$cachedOrders;
    }
}