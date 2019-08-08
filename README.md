# Embed Font Awesome Plugin

The **Embed Font Awesome plugin** for [Grav](http://github.com/getgrav/grav) automatically embeds icons into generated web pages as inline SVGs.

## Installation

### GPM (preferred method)

You can install the plugin by running `bin/gpm install embed-fontawesome` or searching for `embed-fontawesome` in the Admin Panel.

### Manual installation

Alternatively, you can download the zip version of this repository, unzip to `/your/site/grav/user/plugins` and rename the directory to `embed-fontawesome`.

## Configuration

The `embed-fontawesome.yaml` file contains the following configuration options:

- `enabled` (bool): Enables/disables the plugin.
- `builtin_css` (bool): Enables/disables loading of the builtin CSS.
- `fail_behaviour` (`hard`/`soft`): Behaviour when icon files are missing (`hard` => throw an exception, `soft` => replace the icon with a question mark).
- `icon_class` (text): Class(es) to assign to the inline SVG icons (multiple classes should be space-separated).
- `retain_icon_name` (bool): Whether to retain the icon name as a class of the icon (eg. `fa-heart`).
- `emoji_icons` (bool): Whether to enable the :emoji:-syntax for custom icons.
- `shortcode_icons` (bool): Whether to enable the `[icon=.../]` shortcode for custom icons.

### Uploading icons

Icons can be uploaded either via the plugin configuration in the Admin panel, or by copying them to `user/data/fontawesome/VARIANT/*.svg` (eg. `user/data/fontawesome/solid/heart.svg`). Only files uploaded via the Admin panel will appear there, but all will be available to the plugin. Custom/non-Font-Awesome icons can be uploaded/added in the same way to the `user/data/fontawesome/custom` folder.

## Example

Once the full output of a page has been generated, but before it has been cached, this plugin identifies icons in the HTML and replaces them with inline SVGs. Additional classes and attributes have been omitted in the example below for the sake of clarity, but will be preserved and become properties of the `span` element if they are present.

**Note:** The first `fa-*` class after the variant is taken as the icon name, so if you want to have a solid heart that spins, you should write these classes as `fas fa-heart fa-spin`.

### Input

The plugin recognises icons as empty `i` elements with Font Awesome classes (eg. `fas fa-heart`).

```html
<i class="fas fa-heart"></i>
```

### Output

The first instance of the icon will be replaced with the inline SVG.

```html
<span class="icon">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" id="fas_fa-heart">
    <path d="..."></path>
  </svg>
</span>
```

Further instances of the same icon will be replaced with a reference back to the first instance.

```html
<span class="icon">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
    <use href="#fas_fa-heart" />
  </svg>
</span>
```

The `viewBox` for some icons varies, so this plugin also ensures that later references have the correct `viewBox`.

## Inserting custom icons

This plugin also provides a number of methods for inserting custom icons into your content to complement the methods for inserting Font Awesome icons. These will be identified and replaced with inline SVGs alongside the Font Awesome icons.

In all of the examples below, `NAME` refers to the name of the icon file. For custom icons these will be those stored at `user/data/fontawesome/custom/NAME.svg`.

### HTML

In the same way that Font Awesome icons can be included via `<i class="fas fa-NAME"></i>`, custom icons can be included via `<i class="icon icon-NAME"></i>`.

### Emoji-style

An :emoji:-style method is provided to complement the [Markdown Font Awesome plugin](https://github.com/n-parsons/grav-plugin-markdown-fontawesome).

```md
:icon-NAME:
:icon icon-NAME:
:icon-NAME additional classes:
:icon icon-NAME additional classes:
```

**Note:** This method conflicts with Markdown Extra definition lists. If Markdown Extra is enabled, icons cannot be placed at the start of a line and must have at least one non-whitespace character preceding them. The definition list syntax takes precedence here, so icons will break if placed at the start of a line, but all of the Markdown Extra functionality should remain intact.

### Shortcode

A shortcode is provided to complement the `[fa=.../]` shortcode provided by the [Shortcode Core plugin](https://github.com/getgrav/grav-plugin-shortcode-core); this method depends on Shortcode Core, so you will need to have that plugin installed if you want to use this shortcode.

```md
[icon=NAME /]
[icon icon=NAME /]
[icon=NAME extras=additional,classes /]
[icon icon=NAME extras=additional,classes /]
```

## License

This project is available under the permissive [MIT license](LICENSE).
