<?php

/**
 * Class FP_Source_Feed_CPT registers our custom post type and sets up meta boxes
 * for it.
 */

class FP_Source_Feed_CPT {

    private static $_instance;

	/**
	 * Setup actions and filters
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
        add_action( 'init', array( $this, 'setup_cpt' ) );
		add_filter( 'post_updated_messages', array( $this, 'filter_post_updated_messages' ) );
		add_action( 'admin_enqueue_scripts' , array( $this, 'action_admin_enqueue_scripts_css' ) );
		add_filter( 'enter_title_here', array( $this, 'filter_enter_title_here' ), 10, 2 );
		add_action( 'save_post', array( $this, 'action_save_post' ) );
		add_filter( 'manage_fp_feed_posts_columns' , array( $this, 'filter_columns' ) );
		add_action( 'manage_fp_feed_posts_custom_column' , array( $this, 'action_custom_columns' ), 10, 2 );
		add_action( 'post_submitbox_misc_actions', array( $this, 'action_post_submitbox_misc_actions' ) );
		add_action( 'before_delete_post', array( $this, 'action_delete_post' ) );
    }

	/**
	 * Mark a post as deleted if it has been syndicated
	 *
	 * @param int $post_id
	 * @since 0.1.6
	 */
	public function action_delete_post( $post_id ) {

		$source_feed_id = get_post_meta( $post_id, 'fp_source_feed_id', true );

		if ( ! empty( $source_feed_id ) ) {

			$guid = get_post_meta( $post_id, 'fp_guid', true );

			if ( ! empty( $guid ) ) {
				$guid = esc_url_raw( $guid );

				$deleted_posts = get_option( FP_DELETED_OPTION_NAME, array() );

				if ( ! in_array( $guid, $deleted_posts ) ) {
					$deleted_posts[] = $guid;

					update_option( FP_DELETED_OPTION_NAME, $deleted_posts );
				}
			}
		}
	}

	/**
	 * Enqueue post new/edit screen scripts/styles
	 *
	 * @since 0.1.0
	 */
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
			wp_localize_script( 'fp-post-admin', 'FP_Settings', array(
				'pull_nonce' => wp_create_nonce( 'fp_pull_nonce' ),
				'get_namespaces_nonce' => wp_create_nonce( 'fp_get_namespaces_nonce' ),
				'prefixed_root_namespace' => __( 'Define custom namespaces for use in XPath queries. This is totally optional and probably not necessary for most feeds.', 'feed-pull' ),
				'unprefixed_root_namespace' => __( "Usually custom namespaces don't need to be defined, however your feed contains a document level unprefixed namespace which doesn't work well with XPath. You should define a namespace and prefix all your XPath queries with it. For example instead of //feed/entry, use //default:feed/default:entry. Your custom namespace should be automatically defined below.", 'feed-pull' )
			) );

			wp_enqueue_style( 'fp-post-admin', plugins_url( $css_path, dirname( __FILE__ ) ) );
		}
	}

	/**
	 * This method exists so required source feed fields are filterable
	 *
	 * @since 0.1.0
	 * @return mixed|void
	 */
	public static function get_required_fields() {
		$required_fields = array(
			'post_title',
			'guid',
		);

		return apply_filters( 'fp_required_source_feed_fields', $required_fields );
	}

	/**
	 * Filter CPT messages
	 *
	 * @param array $messages
	 * @since 0.1.0
	 * @return array
	 */
	public function filter_post_updated_messages( $messages ) {
		global $post, $post_ID;

		$messages['fp_feed'] = array(
			0 => '',
			1 => sprintf( __( 'Source feed updated. <a href="%s">View source feed</a>', 'feed-pull' ), esc_url( get_permalink( $post_ID ) ) ),
			2 => __( 'Custom field updated.', 'feed-pull' ),
			3 => __( 'Custom field deleted.', 'feed-pull' ),
			4 => __( 'Source feed updated.', 'feed-pull' ),
			5 => isset( $_GET['revision']) ? sprintf( __(' Content feed restored to revision from %s', 'feed-pull' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __( 'Source feed published. <a href="%s">View source feed</a>', 'feed-pull' ), esc_url( get_permalink( $post_ID) ) ),
			7 => __( 'Source feed saved.', 'feed-pull' ),
			8 => sprintf( __( 'Source feed submitted. <a target="_blank" href="%s">Preview source feed</a>', 'feed-pull' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
			9 => sprintf( __( 'Source feed scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview source feed</a>', 'feed-pull' ),
				date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ) ),
			10 => sprintf( __( 'Source feed draft updated. <a target="_blank" href="%s">Preview source feed</a>', 'feed-pull'), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
		);

		return $messages;
	}

	/**
	 * Register source feed post type
	 *
	 * @since 0.1.0
	 */
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
			'menu_icon' => plugins_url( '/img/feed-document.png', dirname( __FILE__ ) ),
			'register_meta_box_cb' => array( $this, 'add_meta_boxes' ),
			'supports' => array( 'title' )
		);

		register_post_type( 'fp_feed', $args );
	}

	/**
	 * Register meta boxes
	 *
	 * @since 0.1.0
	 */
	public function add_meta_boxes() {
		add_meta_box( 'fp_source_details', __( 'Source Feed Details', 'feed-pull' ), array( $this, 'meta_box_source_details' ), 'fp_feed', 'normal', 'core' );
		add_meta_box( 'fp_content_details', __( 'New Content Details', 'feed-pull' ), array( $this, 'meta_box_content_details' ), 'fp_feed', 'normal', 'core' );
		add_meta_box( 'fp_field_mapping', __( 'Field Mapping', 'feed-pull' ), array( $this, 'meta_box_field_mapping' ), 'fp_feed', 'normal', 'core' );
		add_meta_box( 'fp_log', __( 'Pull Log', 'feed-pull' ), array( $this, 'meta_box_log' ), 'fp_feed', 'normal', 'core' );
		add_meta_box( 'fp_manual_pull', __( 'Manual Pull', 'feed-pull' ), array( $this, 'meta_box_manual_pull' ), 'fp_feed', 'side', 'core' );
	}

	public function meta_box_manual_pull( $post ) {
	?>
		<p><?php _e( 'Click this button to manually pull from this feed otherwise you will have to wait for the cron job to execute.', 'feed-pull' ); ?></p>
		<div class="button-container">
			<input type="button" class="button" value="<?php _e( 'Do Feed Pull', 'feed-pull' ); ?>" id="fp_manual_pull">
			<img id="fp-spinner" src="<?php echo includes_url( '/images/wpspin.gif' ); ?>">
		</div>
	<?php
	}

	/**
	 * Change title text box label
	 *
	 * @param string $label
	 * @param int $post
	 * @since 0.1.0
	 * @return string
	 */
	public function filter_enter_title_here( $label, $post = 0 ) {
		if ( 'fp_feed' != get_post_type( $post->ID ) )
			return $label;

		return __( 'Enter source feed name', 'feed-pull' );
	}

	/**
	 * Output source options meta box
	 *
	 * @since 0.1.0
	 * @param $post
	 */
	public function meta_box_source_details( $post ) {
		wp_nonce_field( 'fp_source_details_action', 'fp_source_details' );

		$feed_url = get_post_meta( $post->ID, 'fp_feed_url', true );
		if ( ! empty( $feed_url ) )
			$feed_url = esc_url( $feed_url );

		$posts_xpath = get_post_meta( $post->ID, 'fp_posts_xpath', true );
		if ( ! empty( $posts_xpath ) )
			$posts_xpath = esc_attr( $posts_xpath );

		$namespace_prefix = get_post_meta( $post->ID, 'fp_namespace_prefix', true );
		if ( ! empty( $namespace_prefix ) )
			$namespace_prefix = esc_attr( $namespace_prefix );

		$namespace_url = get_post_meta( $post->ID, 'fp_namespace_url', true );
		if ( ! empty( $namespace_url ) )
			$namespace_url = esc_url( $namespace_url );

		$custom_namespaces = get_post_meta( $post->ID, 'fp_custom_namespaces', true );

	?>
		<p><em><?php _e( 'Tell us about the feed from which we are pulling.', 'feed-pull' ); ?></em></p>
		<p>
			<label for="fp_feed_url"><?php _e( 'Source Feed URL:', 'feed-pull' ); ?></label> <input class="regular-text" type="text" id="fp_feed_url" name="fp_feed_url" value="<?php echo $feed_url; ?>" />
		</p>
		<p>
			<label for="fp_posts_xpath"><?php _e( 'XPath to Posts:', 'feed-pull' ); ?></label> <input class="regular-text" type="text" id="fp_posts_xpath" name="fp_posts_xpath" value="<?php echo $posts_xpath; ?>" />
			<?php _e( '(i.e. channel/item)', 'feed-pull' ); ?>
		</p>

		<p>
			<?php _e( 'Custom Namespaces:', 'feed-pull' ); ?>
		</p>
		<p class="custom-namespaces-description"><?php _e( 'Define custom namespaces for use in XPath queries. This is totally optional and probably not necessary for most feeds.', 'feed-pull' ); ?></p>
		<table cellpadding="0" cellspacing="0" <?php if ( empty( $custom_namespaces ) ) : ?>class="hide"<?php endif; ?>>
			<thead>
			<tr>
				<th><?php _e( 'Namespace Prefix', 'feed-pull' ); ?></th>
				<th><?php _e( 'Namespace URL', 'feed-pull' ); ?></th>
				<th class="action"></th>
			</tr>
			</thead>
			<tbody>
			<?php if ( ! empty( $custom_namespaces ) ) : foreach ( $custom_namespaces as $row_id => $namespace ) : ?>
				<tr data-namespace-row-id="<?php echo (int) $row_id; ?>">
					<td>
						<input type="text" name="fp_custom_namespaces[<?php echo (int) $row_id; ?>][namespace_prefix]" value="<?php if ( ! empty( $namespace['namespace_prefix'] ) ) echo esc_attr( $namespace['namespace_prefix'] ); ?>">
					</td>
					<td>
						<input type="text" name="fp_custom_namespaces[<?php echo (int) $row_id; ?>][namespace_url]" value="<?php if ( ! empty( $namespace['namespace_url'] ) ) echo esc_url( $namespace['namespace_url'] ); ?>">
					</td>
					<td class="action">
						<input type="button" class="button delete" value="<?php _e( 'Delete', 'feed-pull' ); ?>">
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
		<p>
			<input type="button" class="add-new button" value="<?php _e( 'Add Custom Namespace', 'feed-pull' ); ?>">
		</p>

		<script type="text/underscores" id='namespace-row-template'>
			<tr data-namespace-row-id="{{ rowID }}">
				<td>
					<input type="text" name="fp_custom_namespaces[{{ rowID }}][namespace_prefix]" value="{{ namespace_prefix }}">
				</td>
				<td>
					<input type="text" name="fp_custom_namespaces[{{ rowID }}][namespace_url]" value="{{ namespace_url }}">
				</td>
				<td class="action">
					<input type="button" value="<?php _e( 'Delete', 'feed-pull' ); ?>" class="button delete">
				</td>
			</tr>
		</script>
	<?php
	}

	/**
	 * Output new post options meta box
	 *
	 * @since 0.1.0
	 * @param $post
	 */
	public function meta_box_content_details( $post ) {
		wp_nonce_field( 'fp_new_content_details_action', 'fp_new_content_details' );

		$current_post_type = get_post_meta( $post->ID, 'fp_post_type', true );
		$current_post_status = get_post_meta( $post->ID, 'fp_post_status', true );
		$allow_updates = get_post_meta( $post->ID, 'fp_allow_updates', true );
		$smart_author_mapping = get_post_meta( $post->ID, 'fp_smart_author_mapping', true );
		$current_cats = get_post_meta( $post->ID, 'fp_new_post_categories', true );
		if ( empty( $current_cats ) ) {
			$current_cats = array();
		}

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_statii = get_post_statuses();
		?>
		<p><em><?php _e( 'Configure the content we pull from the feed.', 'feed-pull' ); ?></em></p>
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
		<p>
			<label for="fp_allow_updates"><?php _e( 'Update Existing Posts:', 'feed-pull' ); ?></label>
			<select type="text" id="fp_allow_updates" name="fp_allow_updates">
				<option  value="0"><?php _e( 'No', 'feed-pull' ); ?></option>
				<option <?php selected( $allow_updates, 1 ); ?> value="1"><?php _e( 'Yes', 'feed-pull' ); ?></option>
			</select>
		</p>

		<?php $cats = get_categories( array( 'hide_empty' => 0 ) ); ?>
		<p>
			<label for="fp_new_post_categories"><?php _e( 'Automatically Add New Posts to Categories:', 'feed-pull' ); ?></label>
			<select id="fp_new_post_categories" name="fp_new_post_categories[]" multiple="multiple">
				<?php foreach ( $cats as $cat ) : ?>
					<option <?php selected( in_array( $cat->term_id, $current_cats ), true ); ?> value="<?php echo (int) $cat->term_id; ?>"><?php echo esc_html( $cat->category_nicename ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
	<?php
	}

	/**
	 * Output source feed pull log
	 *
	 * @since 0.1.0
	 * @param $post
	 */
	public function meta_box_log( $post ) {

		$log = get_post_meta( $post->ID, 'fp_last_pull_log', true );
	?>

		<?php if ( empty( $log ) ) : ?>
			<p><?php _e( 'No pulls for this source feed yet.', 'feed-pull' ); ?></p>
		<?php else : ?>
			<ul>
			<?php foreach ( $log as $log_item ) : ?>
				<li>
					<span class="<?php echo esc_attr( $log_item['type'] ); ?>">
						<?php echo ucwords( esc_html( $log_item['type'] ) ); ?>
					</span>:
					<?php echo esc_html( $log_item['message'] ); ?>
					<?php if ( ! empty( $log_item['post_id'] ) ) : ?>
					- <?php edit_post_link( __( 'Edit Post', 'feed-pull' ), '', '', $log_item['post_id'] ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
			</ul>

		<?php endif; ?>
	<?php
	}

	/**
	 * Output field mapping meta box
	 *
	 * @since 0.1.0
	 * @param $post
	 */
	public function meta_box_field_mapping( $post ) {
		wp_nonce_field( 'fp_field_mapping_action', 'fp_field_mapping' );

		$field_map = get_post_meta( $post->ID, 'fp_field_map', true );

		?>
		<p><em><?php _e( 'Map fields from your source feed to locations in your new content.', 'feed-pull' ); ?></em></p>
		<table cellpadding="0" cellspacing="0">
			<thead>
				<tr>
					<th><?php _e( 'Source Field (XPath)', 'feed-pull' ); ?></th>
					<th><?php _e( 'New Post Location', 'feed-pull' ); ?></th>
					<th><?php _e( 'Mapping Type', 'feed-pull' ); ?></th>
					<th class="action"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( self::get_required_fields() as $i => $field ) : ?>
					<tr class="req" data-mapping-row-id="<?php echo (int) $i; ?>">
						<td>
							<input type="text" name="fp_field_map[<?php echo (int) $i; ?>][source_field]" value="<?php if ( ! empty( $field_map[$i]['source_field'] ) ) echo esc_attr( $field_map[$i]['source_field'] ); ?>">
						</td>
						<td>
							<input disabled="true" type="text" value="<?php echo esc_attr( $field ); ?>">
							<input type="hidden" name="fp_field_map[<?php echo (int) $i; ?>][destination_field]" value="<?php echo esc_attr( $field ); ?>">
						</td>
						<td>
							<select disabled="true">
								<option value="post_field"><?php _e( 'Post Field', 'feed-pull' ); ?></option>
								<option value="post_meta"><?php _e( 'Post Meta', 'feed-pull' ); ?></option>
								<option value="taxonomy"><?php _e( 'Taxonomy', 'feed-pull' ); ?></option>
							</select>
							<input type="hidden" name="fp_field_map[<?php echo (int) $i; ?>][mapping_type]" value="post_field">
						</td>
						<td class="action">
							<input class="button" style="visibility: hidden" type="button" value="<?php _e( 'Delete', 'feed-pull' ); ?>" class="delete">
						</td>
					</tr>
				<?php endforeach; ?>
			<?php for ( $i = 0; $i < count( self::get_required_fields() ); $i++ ) { if ( isset( $field_map[$i] ) ) unset( $field_map[$i] ); }
			if ( ! empty( $field_map ) ) : foreach ( $field_map as $row_id => $field ) : ?>
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
							<option <?php  if ( ! empty( $field['mapping_type'] ) ) selected( 'taxonomy', $field['mapping_type'] ); ?> value="taxonomy"><?php _e( 'Taxonomy', 'feed-pull' ); ?></option>
						</select>
					</td>
					<td class="action">
						<input type="button" class="button delete" value="<?php _e( 'Delete', 'feed-pull' ); ?>">
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>

		<script type="text/underscores" id='mapping-row-template'>
			<tr data-mapping-row-id="{{ rowID }}">
				<td>
					<input type="text" name="fp_field_map[{{ rowID }}][source_field]" value="">
				</td>
				<td>
					<input type="text" name="fp_field_map[{{ rowID }}][destination_field]" value="">
				</td>
				<td>
					<select name="fp_field_map[{{ rowID }}][mapping_type]">
						<option value="post_field"><?php _e( 'Post Field', 'feed-pull' ); ?></option>
						<option value="post_meta"><?php _e( 'Post Meta', 'feed-pull' ); ?></option>
						<option value="taxonomy"><?php _e( 'Taxonomy', 'feed-pull' ); ?></option>
					</select>
				</td>
				<td class="action">
					<input type="button" value="<?php _e( 'Delete', 'feed-pull' ); ?>" class="button delete">
				</td>
			</tr>
		</script>

		<div class="button-wrapper">
			<input type="button" value="<?php _e( 'Add New', 'feed-pull' ); ?>" class="add-new button">
		</div>
	<?php
	}

	/**
	 * Save information associated with CPT
	 *
	 * @param int $post_id
	 * @since 0.1.0
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

			if ( ! empty( $_POST['fp_custom_namespaces'] ) ) {
				$custom_namespaces = array();

				foreach ( $_POST['fp_custom_namespaces'] as $row_id => $namespace ) {
					$new_namespace = array();

					$new_namespace['namespace_prefix'] = sanitize_text_field( $namespace['namespace_prefix'] );
					$new_namespace['namespace_url'] = esc_url_raw( $namespace['namespace_url'] );

					$custom_namespaces[] = $new_namespace;
				}

				if ( ! empty( $custom_namespaces ) ) {
					update_post_meta( $post_id, 'fp_custom_namespaces', $custom_namespaces );
				}
			} else {
				delete_post_meta( $post_id, 'fp_custom_namespaces' );
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

			if ( ! empty( $_POST['fp_allow_updates'] ) ) {
				update_post_meta( $post_id, 'fp_allow_updates', absint( $_POST['fp_allow_updates'] ) );
			} else {
				delete_post_meta( $post_id, 'fp_allow_updates' );
			}

			if ( ! empty( $_POST['fp_new_post_categories'] ) ) {
				update_post_meta( $post_id, 'fp_new_post_categories', array_map( 'absint', $_POST['fp_new_post_categories'] ) );
			} else {
				delete_post_meta( $post_id, 'fp_new_post_categories' );
			}
		}

		if ( ! empty( $_POST['fp_field_mapping'] ) && wp_verify_nonce( $_POST['fp_field_mapping'], 'fp_field_mapping_action' ) ) {
			if ( ! empty( $_POST['fp_field_map'] ) ) {
				$field_map = array();

				foreach ( $_POST['fp_field_map'] as $row_id => $field ) {
					if ( $row_id >= count( self::get_required_fields() ) && ( empty( $field['source_field'] ) ||
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
	 * Add new columns
	 *
	 * @param array $columns
	 * @since 0.1.0
	 * @return array
	 */
	public function filter_columns( $columns ) {
		$columns['fp_last_pull_time'] = __( 'Last Pulled On', 'my-reviews' );

		// Move date column to the back
		unset($columns['date']);
		$columns['date'] = __( 'Date', 'feed-pull' );

		return $columns;
	}

	public function action_post_submitbox_misc_actions() {
		if ( 'fp_feed' != get_post_type() ) {
			return;
		}

		?>
		<div class="misc-pub-section misc-pub-fp-last-pulled">
			<label><?php _e( 'Last pulled on:', 'feed-pull' ); ?></label>
			<span><strong>
				<?php
				$last_pull = get_post_meta( get_the_ID(), 'fp_last_pull_time', true );
				if ( ! empty( $last_pull ) ) {
					echo date( 'F j, Y, g:i a', (int) $last_pull );
				} else {
					_e( 'Never', 'feed-pull' );
				}
				?>
			</strong></span>
		</div>
		<?php
	}

	/**
	 * Displays custom columns
	 *
	 * @param string $column
	 * @param int $post_id
	 * @since 0.1.0
	 * @return void
	 */
	public function action_custom_columns( $column, $post_id ) {
		if ( 'fp_last_pull_time' == $column ) {
			$last_pull = get_post_meta( $post_id, 'fp_last_pull_time', true );
			if ( ! empty( $last_pull ) ) {
				echo date( 'F j, Y, g:i a', (int) $last_pull );
			} else {
				_e( 'Never', 'feed-pull' );
			}
		}
	}

    /**
     * Return an instance of the current class, create one if it doesn't exist
     *
	 * @since 0.1.0
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