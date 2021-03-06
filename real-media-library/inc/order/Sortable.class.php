<?php
namespace MatthiasWeb\RealMediaLibrary\order;
use MatthiasWeb\RealMediaLibrary\general;
use MatthiasWeb\RealMediaLibrary\folder;
use MatthiasWeb\RealMediaLibrary\attachment;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Handles the sortable content in the folder. The methods of this class contains
 * always the keyword "content".
 */
abstract class Sortable extends folder\Creatable {
    
    static $cachedContentOrders = null;
    
    public function __construct($id, $parent = -1, $name = "", $slug = "", $absolute = "", $order = -1, $cnt = 0, $row = array()) {
        $this->contentCustomOrder = isset($row->contentCustomOrder) ? $row->contentCustomOrder : "2";
        
        // Parent constructor
        parent::__construct($id, $parent, $name, $slug, $absolute, $order, $cnt, $row);
    }
    
    // Documentated in IFolderContent
    public function contentOrder($attachmentId, $nextId, $lastIdInView = false) {
        // Check, if the folder needs the order enabled first
        $contentCustomOrder = $this->getContentCustomOrder();
        if ($contentCustomOrder == 0 && !$this->contentEnableOrder()) {
            throw new \Exception(__("The given folder does not allow to reorder the files.", RML_TD));
        }else if ($contentCustomOrder == "1") { // Reindex
            $this->contentReindex();
        }
        
        $orderAutomatically = $this->getRowData('orderAutomatically');
        if (!empty($orderAutomatically)) {
            throw new \Exception(__('This folder has an automatic order, please deactivate that first.', RML_TD));
        }
        
        /**
         * Allow changing the post ids for the content order process. This is for example
         * necessery when using a multilingual plugin like WPML so the order is always synced
         * when using RML/Sortable/PostsClauses.
         * 
         * @param {array} $ids The ids for attachment, next (can be false) and lastInView (can be false)
         * @return {array} $ids
         * @hook RML/Sortable/Ids
         * @since 4.0.8
         */
        $changed = apply_filters("RML/Sortable/Ids", array(
            'attachment' => $attachmentId,
            'next' => $nextId,
            'lastInView' => $lastIdInView
        ));
        $attachmentId = $changed['attachment'];
        $nextId = $changed['next'];
        $lastIdInView = $changed['lastInView'];
        
        // Process
        global $wpdb;
        $table_name = $this->getTableName("posts");
        $this->debug("The folder $this->id wants to move $attachmentId before $nextId (lastIdInView: $lastIdInView)", __METHOD__);
        
        // Is it the real end?
        if ($nextId === false && $lastIdInView !== false) {
            $this->debug("Want to move to the end of the list and there is a pagination system with the lastIdInView...", __METHOD__);
            $nextIdTo = $this->getAttachmentNextTo($lastIdInView);
            if ($nextIdTo > 0) {
                $nextId = $nextIdTo;
            }
        }
        
        // Push to end
        if ($nextId === false) {
            $newOrder = $this->getContentAggregationNr("MAX") + 1;
            $this->debug("Order the attachment to the end and use the new order value $newOrder...", __METHOD__);
        }else{
            $_newOrder = $this->getContentNrOf($nextId); // Temp save in this, because the query can fail
            if ($_newOrder === false) {
                $_newOrder = $this->getContentAggregationNr("MAX") + 1; // Put to last
            }
            
            $this->debug("Order the attachment before $nextId and change the order value $_newOrder for the moved attachment....", __METHOD__);
            
            // Count up the next ids
            $wpdb->query("UPDATE $table_name SET nr = nr + 1 WHERE fid = $this->id AND nr >= $_newOrder");
            $newOrder = $_newOrder;
        }
        
        // Update the new order number
        if (isset($newOrder) && $newOrder > 0) {
            $wpdb->query($wpdb->prepare("UPDATE $table_name SET nr=%d WHERE fid=%d AND attachment=%d", $newOrder, $this->id, $attachmentId));
            $this->debug("Successfully updated the order of the attachmnet", __METHOD__);
            
            // Save to old custom order
            $wpdb->query($wpdb->prepare("UPDATE " . $table_name . " SET oldCustomNr = nr WHERE fid = %d;", $this->id));
            $this->debug("Successfully updated the old custom nr of the folder", __METHOD__);
            
            /**
             * This action is fired after an item in a folder got ordered by drag&drop.
             * 
             * @param {int} $fid The folder id
             * @param {int} $attachmentId The attachment id which got updated
             * @param {int} $newOrder The new "nr" value
             * @hook RML/Item/DragDrop
             * @since 4.5.3
             */
            do_action("RML/Item/DragDrop", $this->id, $attachmentId, $newOrder);
        }else{
            throw new \Exception(__("Something went wrong.", RML_TD));
        }
    }
    
    // Documentated in IFolderContent
    public function contentOrderBy($orderby, $writeMetadata = true) {
        $orders = self::getAvailableContentOrders();
        $fid = $this->getId();
        $this->debug("Try to order the folder $fid by $orderby...", __METHOD__);
        if (in_array($orderby, array_keys($orders))) {
            global $wpdb;
            
            // Get order
            $split = explode("_", $orderby);
            $order = $orders[$orderby];
            $direction = $split[1];
            $table_name = $this->getTableName("posts");
            
            // Run SQL
            $sql = $wpdb->prepare("UPDATE $table_name AS rmlo2
                LEFT JOIN (
                	SELECT @rownum := @rownum + 1 AS nr, t.ID
                	FROM ( SELECT wp.ID
                		FROM $table_name AS rmlo
                		INNER JOIN $wpdb->posts AS wp ON rmlo.attachment = wp.id AND wp.post_type = \"attachment\"
                		WHERE rmlo.fid = %d
                		ORDER BY " . $order["sqlOrder"] . " $direction ) AS t, (SELECT @rownum := 0) AS r
                ) AS rmlonew ON rmlo2.attachment = rmlonew.ID
                SET rmlo2.nr = rmlonew.nr
                WHERE rmlo2.fid = %d", $fid, $fid);
            $wpdb->query($wpdb->prepare("UPDATE " . $this->getTableName() . " SET contentCustomOrder=1 WHERE id = %d", $fid));
            $wpdb->query($sql);
            
            // Save in the metadata
            if ($writeMetadata) {
                update_media_folder_meta($fid, "orderby", $orderby);
            }
            $this->debug("Successfully ordered folder", __METHOD__);
            
            /**
             * This action is fired after items ins a folder got ordered by criteria.
             * 
             * @param {int} $fid The folder id
             * @param {string} $orderby Orderby column
             * @param {string} $order 'ASC' or 'DESC'
             * @hook RML/Folder/OrderBy
             * @since 4.5.3
             */
            do_action("RML/Folder/OrderBy", $fid, $split[0], $split[1]);
            
            return true;
        }else{
            $this->debug("'$orderby' is not a valid order...", __METHOD__);
            return false;
        }
    }
    
    // Documentated in IFolderContent
    public function contentIndex($delete = true) {
        // Check, if this folder is allowed for custom content order
        if (!$this->isContentCustomOrderAllowed()) {
            return false;
        }
        
        // First, delete the old entries from this folder
        if ($delete && !$this->contentDeleteOrder()) {
            return false;
        }
        
        // Create INSERT-SELECT statement for this folder
        global $wpdb;
        $sql = $wpdb->prepare("UPDATE " . $this->getTableName("posts") . " AS rmlp2
                LEFT JOIN (
                    SELECT
                    	wpp2.ID AS attachment,
                    	wpp2.fid AS fid,
                    	@rownum := @rownum + 1 AS nr,
                    	@rownum AS oldCustomNr
                    FROM (SELECT @rownum := 0) AS r,
                    	(SELECT wpp.ID, rmlposts.fid
                    		FROM $wpdb->posts AS wpp
                    		INNER JOIN " . $this->getTableName("posts") . " AS rmlposts ON ( wpp.ID = rmlposts.attachment )
                    		WHERE rmlposts.fid = %d
                    		AND wpp.post_type = 'attachment'
                    		AND wpp.post_status = 'inherit'
                    		GROUP BY wpp.ID ORDER BY wpp.post_date DESC, wpp.ID DESC) 
                    	AS wpp2
                ) AS rmlnew ON rmlp2.attachment = rmlnew.attachment
                SET rmlp2.nr = rmlnew.nr, rmlp2.oldCustomNr = rmlnew.oldCustomNr
                WHERE rmlp2.fid = %d", $this->id, $this->id);
        $wpdb->query($sql);
        $this->debug("Indexed the content order of $this->id", __METHOD__);
        return true;
    }
    
    // Documentated in IFolderContent
    public function contentReindex() {
        if ($this->getContentCustomOrder() != 1) {
            return false;
        }
        
        global $wpdb;
        $table_name = $this->getTableName("posts");
        $sql = "UPDATE $table_name AS rml2
                LEFT JOIN (
                	SELECT @rownum := @rownum + 1 AS nr, t.attachment
                    FROM ( SELECT rml.attachment
                        FROM $table_name AS rml
                        WHERE rml.fid = $this->id
                        ORDER BY rml.nr ASC )
                        AS t, (SELECT @rownum := 0) AS r
                ) AS rmlnew ON rml2.attachment = rmlnew.attachment
                SET rml2.nr = rmlnew.nr
                WHERE rml2.fid = $this->id";
        
        $wpdb->query($sql);
        $this->debug("Reindexed the content order of $this->id", __METHOD__);
        return true;
    }
    
    // Documentated in IFolderContent
    public function contentEnableOrder() {
        // Check, if this folder is allowed for custom content order
        if (!$this->contentIndex(false)) {
            return false;
        }
        
        global $wpdb;
        $wpdb->query($wpdb->prepare("UPDATE " . $this->getTableName() . " SET contentCustomOrder=1 WHERE id = %d", $this->id));
        $this->contentCustomOrder = 1;
        return true;
    }
    
    // Documentated in IFolderContent
    public function contentDeleteOrder() {
        if ($this->getContentCustomOrder() != 1) {
            return false;
        }
        
        delete_media_folder_meta($this->id, "orderby");
        $this->debug("Deleted order of the folder $this->id", __METHOD__);
        return true;
    }
    
    // Documentated in IFolderContent
    public function contentRestoreOldCustomNr() {
        global $wpdb;
        $wpdb->query($wpdb->prepare("UPDATE " . $this->getTableName("posts") . " SET nr = oldCustomNr WHERE fid=%d;", $this->id));
        delete_media_folder_meta($this->id, "orderAutomatically");
        $this->debug("Restored the order of folder $this->id to the old custom order", __METHOD__);
        return true;
    }
    
    // Documentated in IFolderContent
    public function isContentCustomOrderAllowed() {
        return $this->getContentCustomOrder() != 2;
    }
    
    // Documentated in IFolderContent
    public function getContentCustomOrder() {
        return $this->contentCustomOrder;
    }
    
    // Documentated in IFolderContent
    public function forceContentCustomOrder() {
        return false;
    }
    
    // Documentated in IFolderContent
    public function postsClauses($pieces) {
        return false;
    }
    
    // Documentated in IFolderContent
    public function getAttachmentNextTo($attachmentId) {
        if ($this->getContentCustomOrder() != 1) {
            return false;
        }
        
        global $wpdb;
        $query = new \WP_Query(array(
			'post_status' => 'inherit',
			'post_type' => 'attachment',
			'rml_folder' => $this->id,
			'orderby' => 'rml',
			'order' => 'ASC'
		));
		$sql = str_replace("SQL_CALC_FOUND_ROWS", "", $query->request);
		$sql = $wpdb->prepare('SELECT * FROM (' . $sql . ') tmpnext
		    WHERE orderNr > (SELECT orderNr FROM (' . $sql . ') tmpnext2 WHERE ID = %d)
		    LIMIT 1', $attachmentId);
	    $result = $wpdb->get_row($sql, ARRAY_A);
	    return $result;
    }
    
    // Documentated in IFolderContent
    public function getContentAggregationNr($function = "MAX") {
        if (!in_array($function, array("MAX", "MIN"))) {
            throw new \Exception("Only max or min aggregation function allowed!");
        }
        
        global $wpdb;
        $max = $wpdb->get_var($wpdb->prepare("SELECT " . $function . "(o.nr) FROM " . $this->getTableName("posts") . " AS o WHERE o.fid = %d", $this->id));
        return !($max > 0) ? false : $max;
    }
    
    // Documentated in IFolderContent
    public function getContentNrOf($attachmentId) {
        global $wpdb;
        
        $nextNr = $wpdb->get_var($wpdb->prepare("SELECT o.nr FROM " . $this->getTableName("posts") . " AS o WHERE o.attachment = %d AND o.fid = %d", $attachmentId, $this->id));
        return !($nextNr > 0) ? false : $nextNr;
    }
    
    // Documentated in IFolderContent
    public function getContentOldCustomNrCount() {
        global $wpdb;
        $result = $wpdb->get_col($wpdb->prepare("SELECT COUNT(oldCustomNr) FROM " . $this->getTableName("posts") . " WHERE fid=%d", $this->id));
        return $result[0];
    }
    
    /**
     * Get all available order methods.
     * 
     * @return array
     */
    public static function getAvailableContentOrders($asMap = false) {
        if (self::$cachedContentOrders === null) {
            $orders = array(
                "date_asc" => array(
                    "label" => __("Order by date ascending", RML_TD),
                    "sqlOrder" => "wp.post_date"
                ),
                "date_desc" => array(
                    "label" => __("Order by date descending", RML_TD),
                    "sqlOrder" => "wp.post_date"
                ),
                "title_asc" => array(
                    "label" => __("Order by title ascending", RML_TD),
                    "sqlOrder" => "wp.post_title"
                ),
                "title_desc" => array(
                    "label" => __("Order by title descending", RML_TD),
                    "sqlOrder" => "wp.post_title"
                ),
                "filename_asc" => array(
                    "label" => __("Order by filename ascending", RML_TD),
                    "sqlOrder" => "SUBSTRING_INDEX(wp.guid, '/', -1)"
                ),
                "filename_desc" => array(
                    "label" => __("Order by filename descending", RML_TD),
                    "sqlOrder" => "SUBSTRING_INDEX(wp.guid, '/', -1)"
                ),
                "filenameNat_asc" => array(
                    "label" => __("Natural order by filename ascending", RML_TD),
                    "sqlOrder" => "LENGTH(SUBSTRING_INDEX(wp.guid, '/', -1)), SUBSTRING_INDEX(wp.guid, '/', -1)"
                ),
                "filenameNat_desc" => array(
                    "label" => __("Natural order by filename descending", RML_TD),
                    "sqlOrder" => "LENGTH(SUBSTRING_INDEX(wp.guid, '/', -1)) desc, SUBSTRING_INDEX(wp.guid, '/', -1)"
                ),
                "id_asc" => array(
                    "label" => __("Order by ID ascending", RML_TD),
                    "sqlOrder" => "wp.ID"
                ),
                "id_desc" => array(
                    "label" => __("Order by ID descending", RML_TD),
                    "sqlOrder" => "wp.ID"
                )
            );
            /**
             * Add an available order criterium to folder content. If you pass
             * user input to the SQL Order please be sure the values are escaped!
             * 
             * @example
             * $orders["id_asc"] = array(
             *  "label" => __("Order by ID ascending", RML_TD),
             *  "sqlOrder" => "wp.ID"
             * )
             * @param {object[]} $orders The available orders
             * @return {object[]}
             * @hook RML/Order/Orderby
             */
            self::$cachedContentOrders = apply_filters("RML/Order/Orderby", $orders);
        }
        
        if ($asMap) {
            $sortables = array();
            foreach (self::$cachedContentOrders as $key => $value) {
                $sortables[$key] = $value["label"];
            }
            return $sortables;
        }
        
        return self::$cachedContentOrders;
    }
    
    /**
     * When moving to a folder with content custom order, reindex the folder content.
     * 
     * @hooked RML/Item/MoveFinished
     */
    public static function item_move_finished($folderId, $ids, $folder, $isShortcut) {
        $core = general\Core::getInstance();
        
        // Apply automatic order
        $orderAutomatically = (boolean) $folder->getRowData('orderAutomatically');
        if ($orderAutomatically) {
            $order = $folder->getRowData('lastOrderBy');
            
            if (!empty($order)) {
                $core->debug("$folderId detected some new files, synchronize with automatic orderby...", __METHOD__);
                return $folder->contentOrderBy($order, false);
            }
        }
        
        if ($folder->getContentCustomOrder() == 1) {
            $core->debug("$folderId detected some new files, synchronize with custom content order...", __METHOD__);
            $folder->contentReindex();
        }
    }
    
    /**
     * JOIN the order table and orderby the nr.
     * It is only affected when
     * $query = new \WP_Query(array(
     *      'post_status' => 'inherit',
     *      'post_type' => 'attachment',
     *      'rml_folder' => 4,
     *      'orderby' => 'rml'
     * ));
     */
    public static function posts_clauses($pieces, $query) {
        global $wpdb;
        $folderId = !empty($query->query_vars['parsed_rml_folder']) ? $query->query_vars['parsed_rml_folder'] : 0;
        $applyOrder = $folderId > 0 && (empty($query->query['orderby']) || (isset($query->query['orderby']) && $query->query['orderby'] == "rml") );
        
        if ($query->get('use_rml_folder') !== true) {
            return $pieces;
        }
        
        if ($applyOrder)
            $pieces["orderby"] = $wpdb->posts.  ".post_date DESC, " . $wpdb->posts.  ".ID DESC";
        
        // Get folder
        if ($folderId !== 0) {
            $folder = wp_rml_get_object_by_id($query->query_vars['parsed_rml_folder']);
            if ($folder === null)
                return $pieces;
        }else{
            $folder = wp_rml_get_object_by_id(-1);
        }
        
        $applyOrder = $applyOrder && !($folder->getContentCustomOrder() != 1 && !$folder->forceContentCustomOrder());
        
        // left join and order by
        $pieces["fields"] = trim($pieces["fields"], ",") . ', IFNULL(rmlorder.nr, -1) orderNr';
        $pieces["join"] .= " LEFT JOIN " . general\Core::getInstance()->getTableName("posts") . " AS rmlorder ON rmlorder.fid=IFNULL(rmlposts.fid, 0) AND rmlorder.attachment = " . $wpdb->posts . ".ID ";
        
        if ($folder->postsClauses($pieces) === false && $applyOrder) {
            $pieces["orderby"] = "rmlorder.nr, " . $wpdb->posts.  ".post_date DESC, " . $wpdb->posts.  ".ID DESC";
            
            /**
             * Modify the filter expression for the sortable content. You can str_replace the
             * "rmlorder.nr" for the main order column. Use this filter in conjunction with
             * RML/Sortable/Ids so you can modify the ids for the content order process.
             * 
             * @param {array} $pieces The list of clauses for the query
             * @param {WP_Query} $query The WP_Query instance
             * @param {IFolder} $folder The folder to query
             * @return {array} $pieces
             * @hook RML/Sortable/PostsClauses
             * @see https://developer.wordpress.org/reference/hooks/posts_clauses/
             * @since 4.0.8
             */
	        $pieces = apply_filters("RML/Sortable/PostsClauses", $pieces, $query, $folder);
        }
        
        return $pieces;
    }
    
    /**
     * Media Library Assistent extension.
     */
    public static function mla_media_modal_query_final_terms($query) {
        $folderId = attachment\Filter::getInstance()->getFolder(null, true);
        if ($folderId !== null) {
            $folder = wp_rml_get_object_by_id($folderId);
            if ($folder !== null && $folder->getContentCustomOrder() == 1) {
                $query['orderby'] = "rml";
                $query['order'] = "asc";
            }
        }
        return $query;
    }
    
    /**
     * Deletes the complete order. Use it with CAUTION!
     */
    public static function delete_all_order() {
        global $wpdb;
        $wpdb->query("UPDATE " . general\Core::getInstance()->getTableName("posts") . " SET nr=null, oldCustomNr=NULL");
        $wpdb->query("UPDATE " . general\Core::getInstance()->getTableName() . " SET contentCustomOrder=0");
        general\Core::getInstance()->debug("Deleted the whole order of all folders", __METHOD__);
    }
}

?>