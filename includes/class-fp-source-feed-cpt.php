<?php


class FP_Source_Feed_CPT {

    private static $_instance;


    public function __construct() {
        add_action( 'init', array( $this, 'setup_cpt' ) );
		add_filter( 'post_updated_messages', array( $this, 'filter_post_updated_messages' ) );
		add_action( 'admin_enqueue_scripts' , array( $this, 'action_admin_enqueue_scripts_css' ) );
		add_filter( 'enter_title_here', array( $this, 'filter_enter_title_here' ), 10, 2 );
		add_action( 'save_post', array( $this, 'action_save_post' ) );
    }

	public function action_admin_enqueue_scripts_css() {
		global $pagenow;

		if ( ( 'fp_feed' == get_post_type() || ( isset( $_GET['post_type'] ) && 'fp_feed' == $_GET['post_type'] ) ) &&
			( 'post.php' == $pagenow || 'post-new.php' == $pagenow ) ) {

			if ( defined( WP_DEBUG ) && WP_DEBUG ) {
				$js_path = '/js/post-admin.js';
				$css_path = '/css/post-admin.css';
			} else {
				$js_path = '/build/js/post-admin.min.js';
				$css_path = '/build/css/post-admin.min.css';
			}

			wp_enqueue_script( 'fp-post-admin', plugins_url( $js_path, dirname( __FILE__ ) ), array( 'jquery', 'underscore' ), '1.0', true );

			wp_enqueue_style( 'fp-post-admin', plugins_url( $css_path, dirname( __FILE__ ) ) );
		}
	}

	/**
	 * Filter CPT messages
	 *
	 * @param array $messages
	 * @since 0.1
	 * @uses get_permalink, esc_url, wp_post_revision_title, __, add_query_arg
	 * @return array
	 */
	public function filter_post_updated_messages( $messages ) {
		global $post, $post_ID;

		$messages['fp_feed'] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => sprintf( __( 'Content feed updated. <a href="%s">View content feed</a>', 'feed-pull' ), esc_url( get_permalink( $post_ID ) ) ),
			2 => __( 'Custom field updated.', 'feed-pull' ),
			3 => __( 'Custom field deleted.', 'feed-pull' ),
			4 => __( 'Content feed updated.', 'feed-pull' ),
			5 => isset( $_GET['revision']) ? sprintf( __(' Content feed restored to revision from %s', 'feed-pull' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __( 'Content feed published. <a href="%s">View content feed</a>', 'feed-pull' ), esc_url( get_permalink( $post_ID) ) ),
			7 => __( 'Content feed saved.', 'feed-pull' ),
			8 => sprintf( __( 'Content feed submitted. <a target="_blank" href="%s">Preview review</a>', 'feed-pull' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
			9 => sprintf( __( 'Content feed scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview content feed</a>', 'feed-pull' ),
				date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ) ),
			10 => sprintf( __( 'Content feed draft updated. <a target="_blank" href="%s">Preview content feed</a>', 'feed-pull'), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
		);

		return $messages;
	}

	public function setup_cpt() {

		$labels = array(
			'name' => __( 'Source Feeds', 'feed-pull' ),
			'singular_name' => __( 'Source Feed', 'feed-pull' ),
			'add_new' => __( 'Add New', 'feed-pull' ),
			'add_new_item' => __( 'Add New Source Feed', 'feed-pull' ),
			'edit_item' => __( 'Edit Source Feed', 'feed-pull' ),
			'new_item' => __( 'New Source Feed', 'feed-pull' ),
			'all_items' => __( 'All Source Feeds', 'feed-pull' ),
			'view_item' => __( 'View Source Feed', 'feed-pull' ),
			'search_items' => __( 'Search Source Feeds', 'feed-pull' ),
			'not_found' => __( 'No Source feeds found', 'feed-pull' ),
			'not_found_in_trash' => __( 'No source feeds found in trash', 'feed-pull' ),
			'parent_item_colon' => '',
			'menu_name' => __( 'Source Feeds', 'feed-pull' ),
		);

		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => false,
			'show_ui' => true,
			'show_in_menu' => true,
			'query_var' => false,
			'rewrite' => false,
			'capability_type' => 'post',
			'hierarchical' => false,
			'register_meta_box_cb' => array( $this, 'add_meta_boxes' ),
			'supports' => array( 'title' )
		);

		register_post_type( 'fp_feed', $args );
	}

	/**
	 * Register meta boxes
	 */
	public function add_meta_boxes() {
		add_meta_box( 'fp_source_details', __( 'Source Feed Details', 'feed-pull' ), array( $this, 'meta_box_source_details' ), 'fp_feed', 'normal', 'core' );
		add_meta_box( 'fp_content_details', __( 'New Content Details', 'feed-pull' ), array( $this, 'meta_box_content_details' ), 'fp_feed', 'normal', 'core' );
		add_meta_box( 'fp_field_mapping', __( 'Field Mapping', 'feed-pull' ), array( $this, 'meta_box_field_mapping' ), 'fp_feed', 'normal', 'core' );
	}

	/**
	 * Change title text box label
	 *
	 * @param string $label
	 * @param int $post
	 * @since 0.1
	 * @return string
	 */
	public function filter_enter_title_here( $label, $post = 0 ) {
		if ( 'fp_feed' != get_post_type( $post->ID ) )
			return $label;

		return __( 'Enter source feed name', 'feed-pull' );
	}

	public function meta_box_source_details( $post ) {
		wp_nonce_field( 'fp_source_details_action', 'fp_source_details' );

		$feed_url = get_post_meta( $post->ID, 'fp_feed_url', true );
		if ( ! empty( $feed_url ) )
			$feed_url = esc_url( $feed_url );

		$posts_xpath = get_post_meta( $post->ID, 'fp_posts_xpath', true );
		if ( ! empty( $posts_xpath ) )
			$posts_xpath = esc_attr( $posts_xpath );

		$xml_namespace = get_post_meta( $post->ID, 'fp_xml_namespace', true );
		if ( ! empty( $xml_namespace ) )
			$xml_namespace = esc_attr( $xml_namespace );

	?>
		<p>
			<label for="fp_feed_url"><?php _e( 'Source Feed URL:', 'feed-pull' ); ?></label> <input class="regular-text" type="text" id="fp_feed_url" name="fp_feed_url" value="<?php echo $feed_url; ?>" />
		</p>
		<p>
			<label for="fp_xml_namepsace"><?php _e( 'XML Namespace:', 'feed-pull' ); ?></label> <input class="regular-text" type="text" id="fp_xml_namespace" name="fp_xml_namespace" value="<?php echo $xml_namespace; ?>" />
		</p>
		<p>
			<label for="fp_posts_xpath"><?php _e( 'XPath to Posts:', 'feed-pull' ); ?></label> <input class="regular-text" type="text" id="fp_posts_xpath" name="fp_posts_xpath" value="<?php echo $posts_xpath; ?>" />
		</p>
	<?php
	}

	public function meta_box_content_details( $post ) {
		wp_nonce_field( 'fp_new_content_details_action', 'fp_new_content_details' );

		$current_post_type = get_post_meta( $post->ID, 'fp_post_type', true );
		$current_post_status = get_post_meta( $post->ID, 'fp_post_status', true );

		$post_types = get_post_types( array( 'public' => true, '_builtin' => false ), 'names' );
		$post_statii = get_post_statuses();
		?>
		<p>Configure the content we pull from the feeds.</p>
		<p>
			<label for="fp_post_type"><?php _e( 'Post Type:', 'feed-pull' ); ?></label>
			<select type="text" id="fp_post_type" name="fp_post_type">
				<?php foreach ( $post_types as $post_type ) : if ( 'fp_feed' == $post_type ) continue; ?>
					<option <?php selected( $post_type, $current_post_type ); ?>><?php echo esc_attr( $post_type ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="fp_post_status"><?php _e( 'Post Status:', 'feed-pull' ); ?></label>
			<select type="text" id="fp_post_status" name="fp_post_status">
				<?php foreach ( $post_statii as $post_status => $post_status_pretty ) : ?>
					<option <?php selected( $post_status, $current_post_status ); ?>><?php echo esc_attr( $post_status ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
	<?php
	}

	public function meta_box_field_mapping( $post ) {
		wp_nonce_field( 'fp_field_mapping_action', 'fp_field_mapping' );

		$field_map = get_post_meta( $post->ID, 'fp_field_map', true );

		?>
		<p><?php _e( 'Map fields from your source feed to fields in your new content.', 'feed-pull' ); ?></p>
		<table cellpadding="0" cellspacing="0">
			<thead>
				<tr>
					<th><?php _e( 'Source Field (XPath)', 'feed-pull' ); ?></th>
					<th><?php _e( 'New Post Field', 'feed-pull' ); ?></th>
					<th><?php _e( 'Mapping Type', 'feed-pull' ); ?></th>
					<th class="action"></th>
				</tr>
			</thead>
			<tbody>
				<tr class="req" data-mapping-row-id="0">
					<td>
						<input type="text" name="fp_field_map[0][source_field]" value="<?php if ( ! empty( $field_map[0]['source_field'] ) ) echo esc_attr( $field_map[0]['source_field'] ); ?>">
					</td>
					<td>
						<input disabled="true" type="text" value="post_title">
						<input type="hidden" name="fp_field_map[0][destination_field]" value="post_title">
					</td>
					<td>
						<select disabled="true">
							<option value="post_field"><?php _e( 'Post Field', 'feed-pull' ); ?></option>
							<option value="post_meta"><?php _e( 'Post Meta', 'feed-pull' ); ?></option>
						</select>
						<input type="hidden" name="fp_field_map[0][mapping_type]" value="post_field">
					</td>
					<td class="action">
						<input style="visibility: hidden" type="button" value="<?php _e( 'Delete', 'feed-pull' ); ?>" class="delete">
					</td>
				</tr>
			<?php if ( isset( $field_map[0] ) ) unset( $field_map[0] ); foreach ( $field_map as $row_id => $field ) : ?>
				<tr data-mapping-row-id="<?php echo (int) $row_id; ?>">
					<td>
						<input type="text" name="fp_field_map[<?php echo (int) $row_id; ?>][source_field]" value="<?php if ( ! empty( $field['source_field'] ) ) echo esc_attr( $field['source_field'] ); ?>">
					</td>
					<td>
						<input type="text" name="fp_field_map[<?php echo (int) $row_id; ?>][destination_field]" value="<?php if ( ! empty( $field['destination_field'] ) ) echo esc_attr( $field['destination_field'] ); ?>">
					</td>
					<td>
						<select name="fp_field_map[<?php echo (int) $row_id; ?>][mapping_type]">
							<option <?php  if ( ! empty( $field['mapping_type'] ) ) selected( 'post_field', $field['mapping_type'] ); ?> value="post_field"><?php _e( 'Post Field', 'feed-pull' ); ?></option>
							<option <?php  if ( ! empty( $field['mapping_type'] ) ) selected( 'post_meta', $field['mapping_type'] ); ?> value="post_meta"><?php _e( 'Post Meta', 'feed-pull' ); ?></option>
						</select>
					</td>
					<td class="action">
						<input type="button" value="<?php _e( 'Delete', 'feed-pull' ); ?>" class="delete">
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<script type="text/underscores" id='mapping-row-template'>
			<tr data-mapping-row-id="<%- rowID %>">
				<td>
					<input type="text" name="fp_field_map[<%- rowID %>][source_field]" value="">
				</td>
				<td>
					<input type="text" name="fp_field_map[<%- rowID %>][destination_field]" value="">
				</td>
				<td>
					<select name="fp_field_map[<%- rowID %>][mapping_type]">
						<option value="post_field"><?php _e( 'Post Field', 'feed-pull' ); ?></option>
						<option value="post_meta"><?php _e( 'Post Meta', 'feed-pull' ); ?></option>
					</select>
				</td>
				<td class="action">
					<input type="button" value="<?php _e( 'Delete', 'feed-pull' ); ?>" class="delete">
				</td>
			</tr>
		</script>
		<div class="button-wrapper">
			<input type="button" value="<?php _e( 'Add New', 'feed-pull' ); ?>" class="add-new">
		</div>
	<?php
	}

	/**
	 * Save information associated with CPT
	 *
	 * @param int $post_id
	 * @since 0.1
	 * @return void
	 */
	public function action_save_post( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || 'revision' == get_post_type( $post_id ) )
			return;

		if ( ! empty( $_POST['fp_source_details'] ) && wp_verify_nonce( $_POST['fp_source_details'], 'fp_source_details_action' ) ) {
			if ( ! empty( $_POST['fp_feed_url'] ) ) {
				update_post_meta( $post_id, 'fp_feed_url', esc_url_raw( $_POST['fp_feed_url'] ) );
			} else {
				delete_post_meta( $post_id, 'fp_feed_url' );
			}

			if ( ! empty( $_POST['fp_posts_xpath'] ) ) {
				update_post_meta( $post_id, 'fp_posts_xpath', sanitize_text_field( $_POST['fp_posts_xpath'] ) );
			} else {
				delete_post_meta( $post_id, 'fp_posts_xpath' );
			}

			if ( ! empty( $_POST['fp_xml_namespace'] ) ) {
				update_post_meta( $post_id, 'fp_xml_namespace', sanitize_text_field( $_POST['fp_xml_namespace'] ) );
			} else {
				delete_post_meta( $post_id, 'fp_xml_namespace' );
			}
		}

		if ( ! empty( $_POST['fp_new_content_details'] ) && wp_verify_nonce( $_POST['fp_new_content_details'], 'fp_new_content_details_action' ) ) {
			if ( ! empty( $_POST['fp_post_type'] ) ) {
				update_post_meta( $post_id, 'fp_post_type', sanitize_text_field( $_POST['fp_post_type'] ) );
			} else {
				delete_post_meta( $post_id, 'fp_post_type' );
			}

			if ( ! empty( $_POST['fp_post_status'] ) ) {
				update_post_meta( $post_id, 'fp_post_status', sanitize_text_field( $_POST['fp_post_status'] ) );
			} else {
				delete_post_meta( $post_id, 'fp_post_status' );
			}
		}

		if ( ! empty( $_POST['fp_field_mapping'] ) && wp_verify_nonce( $_POST['fp_field_mapping'], 'fp_field_mapping_action' ) ) {
			if ( ! empty( $_POST['fp_field_map'] ) ) {
				$field_map = array();

				foreach ( $_POST['fp_field_map'] as $row_id => $field ) {
					if ( $row_id >= 1 && ( empty( $field['source_field'] ) ||
						empty( $field['destination_field'] ) || empty( $field['mapping_type'] ) ) ) {
						continue;
					}

					$new_field = array();

					$new_field['source_field'] = sanitize_text_field( $field['source_field'] );
					$new_field['destination_field'] = sanitize_text_field( $field['destination_field'] );
					$new_field['mapping_type'] = sanitize_text_field( $field['mapping_type'] );

					$field_map[] = $new_field;
				}

				if ( ! empty( $field_map ) ) {
					update_post_meta( $post_id, 'fp_field_map', $field_map );
				}
			} else {
				delete_post_meta( $post_id, 'fp_field_map' );
			}
		}

	}

    /**
     * Return an instance of the current class, create one if it doesn't exist
     *
     * @return object
     */
    public static function factory() {
        if ( ! self::$_instance ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }
}

FP_Source_Feed_CPT::factory();