<?php
/**
 * Timber starter-theme
 * https://github.com/timber/starter-theme
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since   Timber 0.1
 */

/**
 * If you are installing Timber as a Composer dependency in your theme, you'll need this block
 * to load your dependencies and initialize Timber. If you are using Timber via the WordPress.org
 * plug-in, you can safely delete this block.
 */
$composer_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
  require_once $composer_autoload;
  $timber = new Timber\Timber();
}

/**
 * This ensures that Timber is loaded and available as a PHP class.
 * If not, it gives an error message to help direct developers on where to activate
 */
if ( ! class_exists( 'Timber' ) ) {

  add_action(
    'admin_notices',
    function() {
      echo '<div class="error"><p>Timber not activated. Make sure you activate the plugin in <a href="' . esc_url( admin_url( 'plugins.php#timber' ) ) . '">' . esc_url( admin_url( 'plugins.php' ) ) . '</a></p></div>';
    }
  );

  add_filter(
    'template_include',
    function( $template ) {
      return get_stylesheet_directory() . '/static/no-timber.html';
    }
  );
  return;
}

/**
 * Sets the directories (inside your theme) to find .twig files
 */
Timber::$dirname = array( 'templates', 'views' );

/**
 * By default, Timber does NOT autoescape values. Want to enable Twig's autoescape?
 * No prob! Just set this value to true
 */
Timber::$autoescape = false;


/**
 * We're going to configure our theme inside of a subclass of Timber\Site
 * You can move this to its own file and include here via php's include("MySite.php")
 */
class StarterSite extends Timber\Site {
  /** Add timber support. */
  public function __construct() {
    add_action( 'after_setup_theme', array( $this, 'theme_supports' ) );
    add_filter( 'timber/context', array( $this, 'add_to_context' ) );
    add_filter( 'timber/twig', array( $this, 'add_to_twig' ) );
    add_action( 'init', array( $this, 'register_post_types' ) );
    add_action( 'init', array( $this, 'register_taxonomies' ) );
    parent::__construct();
  }
  /** This is where you can register custom post types. */
  public function register_post_types() {

  }
  /** This is where you can register custom taxonomies. */
  public function register_taxonomies() {

  }

  /** This is where you add some context
   *
   * @param string $context context['this'] Being the Twig's {{ this }}.
   */
  public function add_to_context( $context ) {
    $context['foo']   = 'bar';
    $context['stuff'] = 'I am a value set in your functions.php file';
    $context['notes'] = 'These values are available everytime you call Timber::context();';
    $context['menu']  = new Timber\Menu();
    $context['site']  = $this;
    return $context;
  }

  public function theme_supports() {
    // Add default posts and comments RSS feed links to head.
    add_theme_support( 'automatic-feed-links' );

    /*
     * Let WordPress manage the document title.
     * By adding theme support, we declare that this theme does not use a
     * hard-coded <title> tag in the document head, and expect WordPress to
     * provide it for us.
     */
    add_theme_support( 'title-tag' );

    /*
     * Enable support for Post Thumbnails on posts and pages.
     *
     * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
     */
    add_theme_support( 'post-thumbnails' );

    /*
     * Switch default core markup for search form, comment form, and comments
     * to output valid HTML5.
     */
    add_theme_support(
      'html5',
      array(
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
      )
    );

    /*
     * Enable support for Post Formats.
     *
     * See: https://codex.wordpress.org/Post_Formats
     */
    add_theme_support(
      'post-formats',
      array(
        'aside',
        'image',
        'video',
        'quote',
        'link',
        'gallery',
        'audio',
      )
    );

    add_theme_support( 'menus' );
  }

  /** BEM function to pass in bem style classes.
   *
   */
  public function bem($context, $base_class, $modifiers = array(), $blockname = '', $extra = array()) {
    $classes = [];

    // Add the ability to pass an object as the one and only argument.
    if (is_object($base_class) || is_array($base_class)) {
      $object = (object) $base_class;
      unset($base_class);
      $map = [
        'block' => 'base_class',
        'element' => 'blockname',
        'modifiers' => 'modifiers',
        'extra' => 'extra',
      ];
      foreach ($map as $object_key => $arg_key) {
        if (isset($object->$object_key)) {
          $$arg_key = $object->$object_key;
        }
      }
    }

    // Ensure array arguments.
    if (!empty($modifiers) && !is_array($modifiers)) {
      $modifiers = [$modifiers];
    }
    if (!is_array($extra)) {
      $extra = [$extra];
    }

    // If using a blockname to override default class.
    if ($blockname) {
      // Set blockname class.
      $classes[] = $blockname . '__' . $base_class;
      // Set blockname--modifier classes for each modifier.
      if (isset($modifiers) && is_array($modifiers)) {
        foreach ($modifiers as $modifier) {
          $classes[] = $blockname . '__' . $base_class . '--' . $modifier;
        };
      }
    }
    // If not overriding base class.
    else {
      // Set base class.
      $classes[] = $base_class;
      // Set base--modifier class for each modifier.
      if (isset($modifiers) && is_array($modifiers)) {
        foreach ($modifiers as $modifier) {
          $classes[] = $base_class . '--' . $modifier;
        };
      }
    }
    // If extra non-BEM classes are added.
    if (isset($extra) && is_array($extra)) {
      foreach ($extra as $extra_class) {
        $classes[] = $extra_class;
      };
    }
    $attributes = 'class="' . implode(' ', $classes) . '"';
    return $attributes;
  }

  /**
   * Add Attributes function to pass in multiple attributes including bem style classes.
   */
  public function add_attributes( $context, $additional_attributes = [] ) {
    $attributes = [];

    if ( ! empty( $additional_attributes ) ) {
      foreach ( $additional_attributes as $key => $value ) {
        // If there are multiple items in $value as array (e.g., class: ['one', 'two']).
        if ( is_array( $value ) ) {
          $attributes[] = ( $key . '="' . implode( ' ', $value ) . '"' );
        } else {
          // Handle bem() output (pass in exactly the result).
          if ( strpos( $value, '=' ) !== false ) {
            $attributes[] = $value;
          } else {
            $attributes[] = ( $key . '="' . $value . '"' );
          }
        }
      }
    }

    return implode( ' ', $attributes );
  }


  /** This is where you can add your own functions to twig.
   *
   * @param string $twig get extension.
   */
  public function add_to_twig( $twig ) {
    $twig->addExtension( new Twig\Extension\StringLoaderExtension() );
    $twig->addFunction( new Twig_SimpleFunction('bem', array($this, 'bem'), array('needs_context' => true), array('is_safe' => array('html'))) );
    $twig->addFunction( new Twig_SimpleFunction('add_attributes', array($this, 'add_attributes'), array('needs_context' => true), array('is_safe' => array('html'))) );
    return $twig;
  }

}

new StarterSite();

// Namespaces
add_filter('timber/loader/loader', function($loader){
  $loader->addPath(__DIR__ . "/../components/01-atoms", "atoms");
  $loader->addPath(__DIR__ . "/../components/02-molecules", "molecules");
  $loader->addPath(__DIR__ . "/../components/03-organisms", "organisms");
  $loader->addPath(__DIR__ . "/../components/04-templates", "templates");
  return $loader;
});
