<?php
namespace Grav\Plugin\Shortcodes;

use Thunder\Shortcode\Shortcode\ShortcodeInterface;

class IconShortcode extends Shortcode
{
  public function init()
  {
    $this->shortcode->getHandlers()->add('icon', function(ShortcodeInterface $sc) {
      // Get icon class
      $icon = $sc->getParameter('icon', $this->getBbCode($sc));
      $iconClass = "icon icon-".$icon;

      $this->grav["debugger"]->addMessage($this->getBbCode($sc));

      // Get extra classes ('extras' is the term used for 'fa' in shortcode-core)
      $extras = $sc->getParameter('extras', $this->getBbCode($sc));

      // Check that it wasn't just that the short [icon=.../] form was used.
      $extras = (isset($extras) and $extras !== $icon) ? $extras : '';

      // Combine the classes
      $classes = $iconClass." ".str_replace(",", " ", $extras);

      // Return the icon
      return '<i class="'.$classes.'"></i>';
    });
  }
}
