<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use RuntimeException;

// Load the other parts of this plugin
use Grav\Plugin\EmbedFontAwesomePlugin\IconNotFoundError;


class EmbedFontAwesomePlugin extends Plugin
{
  private $failBehaviour;
  private $iconClass;
  private $retainIconName;

  private $usedIcons = array();

  /**
   * Gets the subscribed events and registers them
   * @return array
   */
  public static function getSubscribedEvents()
  {
    return [
      'onPluginsInitialized' => [
        ['autoload', 100000], // TODO: Remove when plugin requires Grav >=1.7
        ['onPluginsInitialized', 0],
      ]
    ];
  }

  /**
   * Composer autoload.
   *is
   * @return ClassLoader
   */
  public function autoload(): ClassLoader
  {
      return require __DIR__ . '/vendor/autoload.php';
  }

  /**
   * Initialize the plugin, only loading the bits we need
   */
  public function onPluginsInitialized()
  {
    // Don't proceed if we are in the admin plugin
    if ($this->isAdmin()) return;

    // Get config
    $config = $this->config['plugins.embed-fontawesome'];

    // Check if plugin is enabled
    if ($config['enabled']) {
      // Get some configuration options
      $this->failBehaviour = isset($config['fail_behaviour']) ? $config["fail_behaviour"] : "hard";
      $this->iconClass = isset($config['icon_class']) ? $config["icon_class"] : "icon";
      $this->retainIconName = isset($config['retain_icon_name']) ? $config['retain_icon_name'] : false;

      // Define default events
      $events = array(
        'onOutputGenerated' => ['onOutputGenerated', 10], // This needs to run before Advanced Pagecache
        'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0]
      );

      // Enable CSS loading (if enabled)
      if ($config['builtin_css']) {
        $events = array_merge($events, ['onAssetsInitialized' => ['onAssetsInitialized', 0]]);
      }

      // Enable :emoji: style icons in Markdown (if enabled)
      if ($config['emoji_icons']) {
        $events = array_merge($events, ['onMarkdownInitialized' => ['enableEmojiIcons', 0]]);
      }

      // Enable the [icon=... /] shortcode (if enabled)
      if ($config['shortcode_icons']) {
        $events = array_merge($events, ['onShortcodeHandlers' => ['registerIconShortcode', 0]]);
      }

      // Enable the relevant events
      $this->enable($events);
    }
  }

  /**
   * Load the CSS
   */
  public function onAssetsInitialized() {
    $this->grav["assets"]->addCss("plugin://embed-fontawesome/css/icons.css");
  }

  /**
   * Load the icons as Twig templates
   */
  public function onTwigTemplatePaths() {
    // Make the icons available as Twig templates
    $templatePath = $this->grav["locator"]->findResource("user-data://fontawesome");

    // Check that it exists to avoid an error message
    if (file_exists($templatePath)) {
      $this->grav['twig']->twig_paths[] = $templatePath;
    }
  }

  /**
   * Enable :emoji: style icons in Markdown
   * Based on: https://github.com/N-Parsons/grav-plugin-markdown-fontawesome
   */
  public function enableEmojiIcons(Event $event)
  {
    $markdown = $event['markdown'];

    // Initialize Text example
    $markdown->addInlineType(':', 'EmojiIcon');

    // Add function to handle this
    $markdown->inlineEmojiIcon = function($excerpt) {
      // Search $excerpt['text'] for regex and store whole matching string in $matches[0], store icon name in $matches[1]
      if (preg_match('/^:(?:icon )?icon-([a-zA-Z0-9- ]+):/', $excerpt['text'], $matches))
      {
        return array(
          'extent' => strlen($matches[0]),
          'element' => array(
            'name' => 'i',
            'text' => '',
            'attributes' => array(
              'class' => $this->iconClass.' icon-'.$matches[1],
            ),
          ),
        );
      }
    };
  }

  /**
   * Enable [icon=... /] shortcode
   */
  public function registerIconShortcode()
  {
    $this->grav['shortcode']->registerAllShortcodes(__DIR__.'/shortcodes');
  }

  /**
   * Embed Font Awesome icons
   */
  public function onOutputGenerated()
  {
    // Check that it's an HTML page, abort if not
    if ($this->grav["page"]->templateFormat() !== 'html') {
      return;
    }

    // Get the rendered content (HTML)
    $content = $this->grav->output;

    $this->grav["debugger"]->addMessage($content);

    // Rewrite the output: embed icons as inline SVGs
    $this->grav->output = $this->embedIcons($content);
  }

  /**
   * Embeds icons into the generated HTML as inline SVGs
   *
   * @param string $content Generated HTML to embed icons in
   * @return string Output HTML with embedded icons
   */
  private function embedIcons($content)
  {
    // Get all matches for icons
    if (version_compare($ver = PHP_VERSION, $req = "7.3.0", '<')) {
      $iconRegex = '/<i (?<preClass>.*?)(?<= )class=(?<quot>"|\')(?<classPreFA>[a-zA-Z0-9 :_-]*)(?<=["\' ])(?<weightFA>(?:fa[srlbd]?)|(?:icon)) (?<classMidFA>((?!((fa)|(icon)))[a-zA-Z0-9 _-]*)*)(?<= )(?<iconType>fa|icon)-(?<iconFA>[a-z0-9-]+)(?<classPostFA>[a-zA-Z0-9 :_-]*)\k<quot>(?<postClass>.*?)><\/i>/';
    } else {
      $iconRegex = '/<i (?<preClass>.*?)(?<= )class=(?<quot>"|\')(?<classPreFA>[a-zA-Z0-9 :_-]*)(?<=( |\k<quot>))(?<weightFA>(?:fa[srlbd]?)|(?:icon)) (?<classMidFA>((?!((fa)|(icon)))[a-zA-Z0-9 _-]*)*)(?<= )(?<iconType>fa|icon)-(?<iconFA>[a-z0-9-]+)(?<classPostFA>[a-zA-Z0-9 :_-]*)\k<quot>(?<postClass>.*?)><\/i>/';
    }

    if (preg_match_all(
      $iconRegex,
      $content,
      $matchesRaw
    )) {
      // Reconfigure the matches into a more useful structure
      foreach($matchesRaw as $n => $set) {
        foreach($set as $m => $match) {
          $matches[$m][$n] = $match;
        }
      }

      // Replace the matches
      foreach($matches as $match) {
        $fullMatch = $match[0];

        // Get the replacement HTML
        $replace = $this->getIconHtml($match);

        // Perform replacement, only replacing the first instance
        $content = str_replace_once($fullMatch, $replace, $content);
      }
    }

    return $content;
  }

  /**
   * Takes an array of components from the regex, and returns the HTML for the inline SVG
   *
   * @param array $match Associative array of regex matches
   * @return string HTML for the inline SVG
   */
  private function getIconHtml($match)
  {
    // Get other attributes
    $attributes = trim($match["preClass"] . " " . $match["postClass"]);
    if ($attributes) {
      $attributes = " " . $attributes;
    }

    // Construct the classes
    $classes = class_merge($this->iconClass, $match["classPreFA"], $match["classMidFA"], $match["classPostFA"]);
    if ($this->retainIconName) {
      $classes = array_merge($classes, [$match["iconType"].'-'.$match["iconFA"]]);
    }
    $classSpan = implode(" ", array_unique($classes));

    // Determine the icon ID
    $iconId = $match["weightFA"]."_".$match["iconType"]."-".$match["iconFA"];

    if (isset($this->usedIcons[$iconId])) {
      // Get the viewBox
      $viewBox = $this->usedIcons[$iconId];

      // Create a twig template
      $twigTemplate = '<span class="' . $classSpan . '"' . $attributes . '><svg xmlns="http://www.w3.org/2000/svg" '.$viewBox.'><use href="#'.$iconId.'" /></svg></span>';

      // Process the template
      $inlineSvg = $this->processIconTemplate($twigTemplate);

    } else {
      // Get the icon path
      $path = $this->getTemplatePath($match["iconFA"], $match["weightFA"]);

      // Create a twig template
      $twigTemplate = '<span class="' . $classSpan . '"' . $attributes . '>{% include "' . $path . '" %}</span>';

      // Process the template
      $inlineSvg = $this->processIconTemplate($twigTemplate);

      // Get the viewBox
      preg_match('/viewBox="[0-9.]+ [0-9.]+ [0-9.]+ [0-9.]+"/', $inlineSvg, $viewBoxMatches);
      $viewBox = $viewBoxMatches[0];

      // Store the key details of this icon
      $this->usedIcons[$iconId] = $viewBox;

      // Insert the ID
      $inlineSvg = str_replace('<svg ', '<svg id="'.$iconId.'" ', $inlineSvg);
    }

    return $inlineSvg;
  }

  /**
   * Gets the template path for a specified icon and variant
   *
   * @param string $icon Icon name
   * @param string $weight Icon weight/variant
   * @return string Template path for the icon
   */
  private function getTemplatePath($icon, $weight) {
    switch ($weight) {
      case "fas":
      case "fa":
        $folder = "solid/";
        break;
      case "far":
        $folder = "regular/";
        break;
      case "fal":
        $folder = "light/";
        break;
      case "fab":
        $folder = "brands/";
        break;
      case "fad":
        $folder = "duotone/";
        break;
      default:  // "icon"
        $folder = "custom/";
    }

    return $folder . $icon . ".svg";
  }

  /**
   * Safely processes an icon Twig template
   *
   * @param string $template Twig template
   * @return string Inline SVG
   */
  private function processIconTemplate($template)
  {
    try {
      $result = $this->grav["twig"]->processString($template);

    } catch (RuntimeException $e) {
      // Extract the path and create a meaningful message
      if (preg_match('/\{% include (.+) %\}/', $template, $matches)) {
        $msg = "Icon not found: ".$matches[1].".";

        // Process the failure as configured
        if ($this->failBehaviour === "soft") {
          // Log the failure
          $this->grav["debugger"]->addMessage($msg);

          // Replace the missing icon with a question mark
          $fallback = file_get_contents(__DIR__."/assets/missing_icon.svg");
          $result = str_replace($matches[0], $fallback, $template);
        } else {
          // Otherwise, throw a more meaningful error
          throw new IconNotFoundError($msg, 404, $e);
        }
      } else {
        // There should always be a match, but if not, something has gone wrong...
        throw $e;
      }
    }

    return $result;
  }
}

/**
 * Replaces only the first instance of a string
 *
 * @param string $search String to search for and replace
 * @param string $replace Replacement for the search term
 * @param string $subject Content to search
 * @return string $subject with the first instance of $search replaced by $replace
 */
function str_replace_once($search, $replace, $subject)
{
  $position = strpos($subject, $search);
  if ($position !== false){
    return substr_replace($subject, $replace, $position, strlen($search));
  } else {
    return $subject;
  }
}


/**
 * Merges HTML classes into an array
 *
 * @param string ...$classes HTML classes to combine
 * @return array Array of HTML classes
 */
function class_merge(...$classes)
{
  $classList = array();

  foreach($classes as $class) {
    $trimmed = trim($class);
    if ($trimmed) {
      $classList = array_merge(
        explode(" ", $trimmed),
        $classList
      );
    }
  };

  return $classList;
}
