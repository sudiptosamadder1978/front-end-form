<?php
/*
*Plugin Name: Submit custom post type from frontend
*Description: My plugin to explain the submit custom post type from frontend functionality. 
*Version: 1.0
*Author: Diwakar Academy
*Author URI: https://diwakaracademy.com/
*/

add_action('wp_enqueue_scripts', 'add_css');

function add_css()
{

    wp_enqueue_style('form-css', plugin_dir_url(__FILE__) . '/css/form.css');
}


add_action('init', 'register_custom_post_types');

function register_custom_post_types()
{

    register_post_type(
        'book',

        [
            'labels' => [

                'name' => 'Books',
                'singular_name' => 'Book',
                'menu_name' => 'Books',
            ],
            'public' => true,
            'publicly_queryable' => true,
            'menu_icon' => 'dashicons-book',
            'has_arvhive' => true,
            'rewrite' => ['slug' => 'book'],
            'supports' => [
                'title',
                'editor',
                'thumbnail',
            ],
        ]

    );
}

function register_taxonomiesss()
{


    register_taxonomy(
        'book_category',
        ['book'],
        [


            'hierarchical' => true,
            'labels' => [
                'name' => 'Categories',
                'singular_name' => 'Category',
                'menu_nam' => 'Categories',
            ],
            'show_ui' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'cat']

        ]

    );
}

add_action('init', 'register_taxonomiesss');


function custom_meta_boxes()
{

    add_meta_box('book_cpt_id', 'ISBN No.', 'book_cpt_callback_func', 'book', 'normal', 'low');
}

add_action('add_meta_boxes', 'custom_meta_boxes');


function book_cpt_callback_func()
{

    wp_nonce_field('basename(__FILE__)', 'wp_book_cpt_nonce');

    $isbn_no = get_post_meta(get_the_ID(), 'isbn_no', true);

?>



<div>
    <label for="isbn_no">ISBN No.</label>
    <input type="text" name="isbn_no" value="<?php echo $isbn_no; ?>">
</div>
<?php
}

add_action('save_post', 'custom_cpt_save_meta_box', 10, 2);

function custom_cpt_save_meta_box($post_id, $post)
{

    if (!isset($_REQUEST['wp_book_cpt_nonce']) || wp_verify_nonce($_REQUEST['wp_book_cpt_nonce'], basename(__FILE__))) {
        return;
    }

    if ('book' != $post->post_type) {
        return;
    }

    if (isset($_REQUEST['isbn_no'])) {

        $isbn_no = sanitize_text_field($_REQUEST['isbn_no']);
        update_post_meta($post_id, 'isbn_no', $isbn_no);
    }
}

add_action('manage_book_posts_columns', 'book_custom_cpt_columns');

function book_custom_cpt_columns()
{

    $custom_columns = [
        'cb' => '<input type="checkbox">',
        'title' => 'Book Title',
        'isbn_no' => 'ISBN No',
        'book_cat' => 'Categories',
        'date' => 'Date',

    ];


    return $custom_columns;
}

add_action('manage_book_posts_custom_column', 'book_cpt_custom_column_data', 10, 2);

function book_cpt_custom_column_data($columns, $post_id)
{

    switch ($columns) {
        case 'isbn_no':
            echo $isbn_no = get_post_meta($post_id, 'isbn_no', true);
            break;

        case 'book_cat':
            $terms = get_the_terms($post_id, 'book_category');
            $out = array();
            if ($terms) foreach ($terms as $term) $out[] = $term->name;
            echo join(', ', $out);
            break;
    }
}

add_filter('manage_edit-book_sortable_columns', 'book_cpt_sortable_columns');

function book_cpt_sortable_columns()
{
    $columns['title'] = 'title';
    $columns['isbn_no'] = 'isbn_no';
    $columns['book_cat'] = 'book_cat';
    $columns['date'] = 'date';
    return $columns;
}

add_shortcode('book_frontend_post', 'book_frontend_post_func');

function book_frontend_post_func()
{

    $errors = 0;
    if (@$_REQUEST['submit'] && wp_verify_nonce($_REQUEST['book_nonce'], 'book_action_nonce')) {

        if (empty($_REQUEST['title'])) {
            $errors++;
        }

        if (empty($_REQUEST['content'])) {
            $errors++;
        }

        if (empty($errors)) {

            $post = [
                'post_title' => wp_strip_all_tags($_REQUEST['title']),
                'post_content' => $_REQUEST['content'],
                'post_type' => 'book',
                'post_status' => 'draft',
                'tax_input' => array(
                    'book_category' => $_REQUEST['cat']
                ),
            ];

            $post_id = wp_insert_post($post);

            if ($_REQUEST['isbn_no'])
                update_post_meta($post_id, 'isbn_no', $_REQUEST['isbn_no']);
            $filename = $_FILES['image']['name'];
            $file = $_FILES['image']['tmp_name'];
            if ($filename) {


                $upload_file = wp_upload_bits($filename, null, @file_get_contents($file));
                if (!$upload_file['error']) {
                    // if succesfull insert the new file into the media library (create a new attachment post type).
                    $wp_filetype = wp_check_filetype($filename, null);

                    $attachment = array(
                        'post_mime_type' => $wp_filetype['type'],
                        'post_parent'    => $post_id,
                        'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    );

                    $attachment_id = wp_insert_attachment($attachment, $upload_file['file'], $post_id);

                    if (!is_wp_error($attachment_id)) {
                        // if attachment post was successfully created, insert it as a thumbnail to the post $post_id.
                        require_once(ABSPATH . "wp-admin" . '/includes/image.php');

                        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);

                        wp_update_attachment_metadata($attachment_id,  $attachment_data);
                        set_post_thumbnail($post_id, $attachment_id);
                    }
                }

                unset($_REQUEST['title']);
                unset($_REQUEST['content']);
            }

            if ($post_id) {
                $msg = "<h5>Saved your post successfully</h5>";
            }
        }
    }

    if (is_user_logged_in()) :
    ?>


<div class="postbox">
    <?php echo @$msg;


            if ($errors) : ?>
    <p style="color:red;">Pleasee fill all the required field</p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <p><label for="title">Book Title *</label><br>
            <input type="text" value="<?php echo @$_REQUEST['title']; ?>" name="title">
        </p>
        <p><label for="content">Book Content *</label><br>
            <textarea name="content" rows="6"><?php echo @$_REQUEST['content']; ?></textarea>
        </p>
        <p><label>Category *</label><br>
            <?php wp_dropdown_categories('show_option_none=Category&taxonomy=book_category&hide_empty=0'); ?>
        </p>
        <p><label for="tags">ISBN No</label><br>
            <input type="text" name="isbn_no">
        </p>
        <p><label for="title">Featured Image</label><br>
            <input type="file" name="image">
        </p>
        <?php wp_nonce_field("book_action_nonce", "book_nonce"); ?>
        <p><input type="submit" name="submit" value="submit">
        </p>
    </form>
</div>
<?php else : ?>
<p>After Login you can submit book information</p>

<?php
    endif;
}