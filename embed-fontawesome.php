<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use RuntimeException;

use EmbedFontAwesomePlugin\IconNotFoundError;

class EmbedFontAwesomePlugin extends Plugin
{
  private $failBehaviour;
  private $iconClass;
  private $retainIconName;

  /**
   * Gets the subscribed events and registers them
   * @return array
   */
  public static function getSubscribedEvents()
  {
    // Load the improved exception type
    require_once(__DIR__."/classes/IconNotFoundError.php");

    return [
      'onPluginsInitialized' => ['onPluginsInitialized', 0]
    ];
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
        'onOutputGenerated' => ['onOutputGenerated', 0],
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
    $templatePath = getcwd() . "/user/data/fontawesome";

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
    $page = $this->grav["page"];
    $content = $this->grav->output;  // HTML

    //$this->grav["debugger"]->addMessage($content);

    if ($page->templateFormat() !== 'html') {
      return;
    }

    // Get all matches for Font Awesome icons
    preg_match_all('/<i (?<preClass>[a-zA-Z0-9 _="-]*)class=(?:"|\')(?<classPreFA>[a-zA-Z0-9 _-]*)(?<weightFA>(?:fa[srlbd]?)|(?:icon)) (?<classMidFA>((?!((fa)|(icon)))[a-zA-Z0-9 _-]*)*)(?<iconType>fa|icon)-(?<iconFA>[a-z0-9-]+)(?<classPostFA>[a-zA-Z0-9 _-]*)(?:"|\')(?<postClass>[a-zA-Z0-9 _="-]*)><\/i>/', $content, $matchesRaw);

    foreach($matchesRaw as $n => $set) {
      foreach($set as $m => $match) {
        $matches[$m][$n] = $match;
      }
    }

    // Replace the matches
    foreach($matches as $match) {
      $fullMatch = $match[0];

      // Get the replacement HTML
      $replace = $this->embedIcon($match);

      // Perform replacement
      $content = str_replace($fullMatch, $replace, $content);
    }

    // Write the new output
    $this->grav->output = $content;
  }

  private function embedIcon($match)
  {
    $twig = $this->grav["twig"];

    $this->grav["debugger"]->addMessage($match["iconFA"]);

    // Get other attributes
    $otherProps = $match["preClass"] . " " . $match["postClass"];

    $path = $this->getPath($match["iconFA"], $match["weightFA"]);

    // Construct the classes
    $classSpan = join(" ", array($this->iconClass, $match["classPreFA"], $match["classMidFA"], $match["classPostFA"]));
    if ($this->retainIconName) {
      $classSpan .= " ".$match["iconType"].'-'.$match["iconFA"];
    }

    // Create a twig template and try to process it
    $twigTemplate = '<span class="' . $classSpan . '"' . $otherProps . '>{% include "' . $path . '" %}</span>';

    try {
      $replace = $twig->processString($twigTemplate);

    } catch (RuntimeException $e) {
      $msg = "Icon not found: ".$path.".";
      if ($this->failBehaviour === "soft") {
        // Replace the missing icon with a question mark
        $fallback = file_get_contents(__DIR__."/assets/missing_icon.svg");
        $replace = '<span class="' . $classSpan . '"' . $otherProps . '>'.$fallback.'</span>';
        $this->grav["debugger"]->addMessage($msg);
      } else {
        // Otherwise, throw a more meaningful error
        throw new IconNotFoundError($msg, 404, $e);
      }
    }

    return $replace;
  }

  private function getPath($icon, $weight) {
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
      default:
        $folder = "custom/";
    }

    return $folder . $icon . ".svg";
  }
}

