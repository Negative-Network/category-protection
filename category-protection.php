<?php
defined('ABSPATH') or die("No direct access");

/**
 * Plugin Name: Category Protection
 * Plugin URI: http://wordpress.org/extend/plugins/category-protection
 * Description: Password protect your categories. Also work for pages if you add a page/category plugin
 * Version: 1.0
 * Author: Negative Network
 * Author URI: http://negative-network.com
 * License: GPL2
 */

/**
 * Add i18n files
 */
function category_protection_load_textdomain() {
    load_plugin_textdomain('category_protection', FALSE, dirname(plugin_basename(__FILE__)) . '/languages/');   
}
add_action( 'plugins_loaded', 'category_protection_load_textdomain' );

/**
 * Set default session vars
 */
function category_protection_init() {
    session_start();
    if(!isset($_SESSION['protected_categories'])) $_SESSION['protected_categories'] = array();
    if(!isset($_SESSION['protected_categories_tries']))
    {
        $_SESSION['protected_categories_tries'] = 0;
        $_SESSION['protected_categories_last_try'] = null;
    }
}
add_action('init', 'category_protection_init');

/**
 * Loads protected categories and compare with current post/page categories to check if password is required
 * override post $content with a form if password required
 * @global post $post the current post/page
 * @param string $content the post/page content
 * @return string the content overriden (or not)
 */
function category_protection($content) {

    global $post;

    $protected_categories = json_decode(get_option('protected_categories'),true);
    $post = get_post();
    
    //it's a post
    if ($post->post_type == 'post' OR $post->post_type == 'page') {

        //get the post categories
        $post_categories = wp_get_post_categories($post);
        
        //if there is protected categories and the post has categories, we perform the check
        if (!empty($protected_categories) and ! empty($post_categories) and is_array($protected_categories) and is_array($post_categories)) {

            //we check if at least one of the post category is protected
            $cats = array_intersect(array_keys($protected_categories), $post_categories);
            
            if (!empty($cats)) { //we have a protected category

                $found = false;
                $error = '';
                
                if(!isset($_SESSION['protected_categories'])) $_SESSION['protected_categories'] = array();
    
                //the password is already set in the cookie
                if (!empty($_SESSION['protected_categories']) AND array_intersect($cats, array_keys($_SESSION['protected_categories'])) ) {
                    $found = true;
                } else
                //if we have a $_POST, we perform the check, if not, we display the form
                if (isset($_POST['category_protection_password']) and $_POST['category_protection_password'] != '') {
                    
                    //reset ban
                    if($_SESSION['protected_categories_last_try'] != null and ( ( strtotime("now") - $_SESSION['protected_categories_last_try'] ) > 300 ) )
                    {
                        $_SESSION['protected_categories_tries'] = 0;
                        $_SESSION['protected_categories_last_try'] = null;
                    }
                    
                    if($_SESSION['protected_categories_tries'] < 5)
                    {
                        $cat_id = array_search($_POST['category_protection_password'], $protected_categories);
                        if ($cat_id) {
                            $_SESSION['protected_categories'][$cat_id] = $_POST['category_protection_password'];
                            $found = true;
                            $_SESSION['protected_categories_tries'] = 0;
                            $_SESSION['protected_categories_last_try'] = null;
                        }
                        else
                        {
                            $_SESSION['protected_categories_tries'] ++;
                            $_SESSION['protected_categories_last_try'] = strtotime("now");
                            $error = __('This password did not work', 'category_protection');
                        }
                    }
                }

                if (!$found) {
                    
                    $categories = get_the_category();
                    $names = array();
                    foreach($categories as $c) {
                        if(key_exists($c->cat_ID, $protected_categories)) $names[$c->cat_ID] = $c->name;
                    }
                    $names = implode(', ',$names);
                    
                    if($_SESSION['protected_categories_tries'] < 5)
                    {
                    $content = '
                        <div style="width:60%;margin: 20px auto;text-align:center;">
                        <h4>' . __('This post belongs to password protected categorie(s)', 'category_protection') . ' ( ' . $names . ' )</h4>
                        <br/><h5>' . __('If you know the password for one of the(se) categorie(s), please enter it here', 'category_protection').'</h5>
                        <br/><form method="post"> 
                            <input type="password" name="category_protection_password"> 
                            <input type="submit" value="' . __('Submit password', 'category_protection') . '" name="category_protection"> 
                        </form><br/>'
                        .$error
                        .'</div>';
                    }
                    else {
                        $content = '<div style="width:60%;margin: 20px auto;text-align:center;">'
                            .__('You failed 5 times in a row to give a correct password. To avoid spamming, you must wait for 5min before trying again', 'category_protection')
                            .'</div>';
                    }
                }
            }
        }
    }

    return $content;
}
add_filter('the_content', 'category_protection');

/**
 * hide comments template if post/page has a password protected category
 */
function category_protection_comments_template($comment_template) {

    global $post;

    if (!isset($_SESSION['protected_categories']))
        $_SESSION['protected_categories'] = array();

    $protected_categories = get_option('protected_categories');
    $post_categories = wp_get_post_categories($post);


    if (!empty($protected_categories) and ! empty($post_categories) and is_array($protected_categories) and is_array($post_categories)) {
        //we check if at least one of the post category is protected
        $cats = array_intersect(array_keys($protected_categories), $post_categories);
        $post = get_post();

        //the password is already set in the cookie
        if (!(!empty($_SESSION['protected_categories']) AND array_intersect($cats, array_keys($_SESSION['protected_categories'])) ))
            return dirname(__FILE__) . '/empty.php';
    }
}

add_filter('comments_template', 'category_protection_comments_template');


/**
 * Admin menu
 */
add_action('admin_menu', 'add_category_protection_setup');

function add_category_protection_setup() {
    add_menu_page(__('Category Protection', 'category_protection'), __('Category Protection', 'category_protection'), 5, basename(__FILE__), 'category_protection_setup');
}

function category_protection_setup() {

    $protected_categories = get_option('protected_categories');

    if (isset($_POST['category_protection_updated'])) {
        update_option('protected_categories', json_encode(array_filter($_POST['passwords']))); //remove categories with empty passwords
        echo '<div class="updated"><p>'.__('Passwords saved', 'category_protection').'</p></div>';
    }

    if (get_option('protected_categories')) {
        $protected_categories = json_decode(get_option('protected_categories'),true);
    } else {
        add_option('protected_categories', json_encode(array()), "Category Protection Values", "yes");
    }
    
    ?>

    <div class="wrap" id="category_protection_setup">

        <h2><?php echo __('Category Protection', 'category_protection'); ?></h2>
        <h3><?php echo __('Enter passwords for the categories you want to protect', 'category_protection'); ?></h3>
        <form name="category_protection_form" method="post" >

            <input type="hidden" id="category_protection_updated" name="category_protection_updated" value="yes" />

            <table class="form-table">
                <tbody>
                    <?php
                    $cats = get_categories();
                    foreach ($cats as $cat)
                    {
                        echo '<tr><td><label>' . $cat->cat_name . '</label></td><td><input type="text" name="passwords[' . $cat->cat_ID . ']" value="' . (isset($protected_categories[$cat->cat_ID]) ? $protected_categories[$cat->cat_ID] : '') . '" /></td></tr>';
                    }
                    ?>
                </tbody>
            </table>

            <p class="submit">
                <input type="submit" name="category_protection_update" value="<?php echo __('Save passwords', 'category_protection'); ?>" class="button-primary" />
            </p>
            
        </form>
        
        <script>
            /**
             * @todo: add a switch button to show/hide passwords
             */
//        jQuery(function(){
////            $('form[name=category_protection_form] input[type=text]').attr('type','password');
//        });
        </script>

        <p>
            <?php echo __("If you feel this plugin has been helpful and you'd like payin' us a beer, you're more than welcome, we love beer! :)", 'category_protection'); ?>
            <br/>
            <br/>
            <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=AKL25S36DARVN" target="_blank"><?php echo __('Go to Paypal to make a donation', 'category_protection'); ?></a>
        </p>
        
    </div>
    <?php
}
?>