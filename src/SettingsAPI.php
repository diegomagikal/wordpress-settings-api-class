<?php

namespace DiegoMagikal;

/**
 * DiegoMagikal Settings API wrapper class
 *
 * @version 1.3 (27-Sep-2016)
 *
 * @author  Tareq Hasan <tareq@weDevs.com>
 * @author  Lucien Plattel <lucien@twindigital.nl>
 * @author  Diego Luiz <magikaldesign@gmail.com>
 * @link    https://tareq.co Tareq Hasan
 * @link    https://twindigital.nl Lucien Plattel
 * @link    https://magikal.com Diego Luiz
 * @example example/oop-example.php How to use the class
 */

/**
 * Class SettingsAPI
 */
class SettingsAPI
{

  /**
   * Settings sections array.
   *
   * @var array $settingsSections
   */
  protected $settingsSections = [];

  /**
   * Settings fields array
   *
   * @var array $settingsFields
   */
  protected $settingsFields = [];

  /**
   * SettingsAPI constructor.
   */
  public function __construct()
  {
    add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
  }

  /**
   * Enqueue scripts and styles
   * @return void
   */
  public function adminEnqueueScripts()
  {
    wp_enqueue_style('wp-color-picker');

    //wp_enqueue_media(); //it generates an error with featured image picker
    wp_enqueue_script('wp-color-picker');
    wp_enqueue_script('jquery');
  }

  /**
   * Set settings sections.
   *
   * @param array $sections Setting sections array.
   *
   * @return \DiegoMagikal\SettingsAPI
   */
  public function setSections(array $sections)
  {
    $this->settingsSections = $sections;

    return $this;
  }

  
  /**
   * Get settings sections.
   *
   * @return array
   */
  public function getSections()
  {
    return $this->settingsSections;
  }



  /**
   * Add a single section
   *
   * @param array $section Array with section settings.
   *
   * @return \DiegoMagikal\SettingsAPI
   */
  public function addSection(array $section): self
  {
    $this->settingsSections[] = $section;

    return $this;
  }

  /**
   * Set settings fields.
   *
   * @param array $fields Settings fields array.
   *
   * @return \DiegoMagikal\SettingsAPI
   */
  public function setFields(array $fields): self
  {
    $this->settingsFields = $fields;

    return $this;
  }

  /**
   * Add a field.
   *
   * @param string $section Name of the section.
   * @param array  $field   The field-settings.
   *
   * @return \DiegoMagikal\SettingsAPI
   */
  public function addField(string $section, array $field): self
  {
    $defaults = [
      'name'  => '',
      'label' => '',
      'desc'  => '',
      'type'  => 'text',
    ];

    $arg                              = wp_parse_args($field, $defaults);
    $this->settingsFields[$section][] = $arg;

    return $this;
  }

  /**
   * Initialize and registers the settings sections and fileds to WordPress
   *
   * Usually this should be called at `adminInit` hook.
   *
   * This function gets the initiated settings sections and fields. Then
   * registers them to WordPress and ready for use.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   *
   * @return void
   */
  public function adminInit()
  { 
    //register settings sections
    foreach ($this->settingsSections as $section) {
      if (array_key_exists('id', $section) === false || get_option($section['id'], false) === false) {
        add_option($section['id']);
      }

      if (isset($section['desc']) === true && empty($section['desc']) === false) {
        $section['desc'] = '<div class="inside">' . $section['desc'] . '</div>';
        $callback        = function () use ($section) {
          echo str_replace('"', '\"', $section['desc']);
        };
      } else if (isset($section['callback']) === true) {
        $callback = $section['callback'];
      } else {
        $callback = null;
      }

      add_settings_section($section['id'], $section['title'], $callback, $section['id']);
    }

    //register settings fields
    foreach ($this->settingsFields as $section => $field) {
      foreach ($field as $option) {
        $name     = $option['name'];
        $type     = (isset($option['type']) === true) ? $option['type'] : 'text';
        $label    = (isset($option['label']) === true) ? $option['label'] : '';
        $callback = (isset($option['callback']) === true) ? $option['callback'] : [$this, 'callback' . ucfirst($type)];

        $args = [
          'id'                => $name,
          'class'             => (isset($option['class']) === true) ? $option['class'] : $name,
          'label_for'         => "{$section}[{$name}]",
          'desc'              => (isset($option['desc']) === true) ? $option['desc'] : '',
          'name'              => $label,
          'section'           => $section,
          'size'              => (isset($option['size']) === true) ? $option['size'] : null,
          'options'           => (isset($option['options']) === true) ? $option['options'] : '',
          'std'               => (isset($option['default']) === true) ? $option['default'] : '',
          'sanitize_callback' => (isset($option['sanitize_callback']) === true) ? $option['sanitize_callback'] : '',
          'type'              => $type,
          'placeholder'       => (isset($option['placeholder']) === true) ? $option['placeholder'] : '',
          'min'               => (isset($option['min']) === true) ? $option['min'] : '',
          'max'               => (isset($option['max']) === true) ? $option['max'] : '',
          'step'              => (isset($option['step']) === true) ? $option['step'] : '',
        ];

        add_settings_field("{$section}[{$name}]", $label, $callback, $section, $section, $args);
      }
    }

    // creates our settings in the options table
    foreach ($this->settingsSections as $section) {
      register_setting($section['id'], $section['id'], [$this, 'sanitizeOptions']);
    }
  }

  /**
   * Displays a url field for a settings field
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackUrl(array $args)
  {
    $this->callbackText($args);
  }

  /**
   * Displays a text field for a settings field
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackText(array $args)
  {
    $value       = esc_attr($this->getOption($args['id'], $args['section'], $args['std']));
    $size        = (isset($args['size']) === true && $args['size'] !== null) ? $args['size'] : 'regular';
    $type        = (isset($args['type']) === true) ? $args['type'] : 'text';
    $placeholder = (empty($args['placeholder']) === true) ? '' : ' placeholder="' . $args['placeholder'] . '"';

    $html = sprintf('<input type="%1$s" class="%2$s-text" id="%3$s[%4$s]" name="%3$s[%4$s]" value="%5$s" %6$s/>', $type, $size, $args['section'], $args['id'], $value, $placeholder);
    $html .= $this->getFieldDescription($args);

    echo $html;
  }

  /**
   * Get the value of a settings field
   *
   * @param string $option  Settings field name.
   * @param string $section The section name this field belongs to.
   * @param mixed  $default Default value if it's not found.
   *
   * @return mixed
   */
  public function getOption(string $option, string $section, $default = false)
  {
    $options = get_option($section);

    if (isset($options[$option]) === true) {
      return $options[$option];
    }

    return $default;
  }

  /**
   * Get field description for display
   *
   * @param array $args Settings field args.
   *
   * @return string
   */
  public function getFieldDescription(array $args): string
  {
    if (empty($args['desc']) === false) {
      $desc = sprintf('<p class="description">%s</p>', $args['desc']);
    } else {
      $desc = '';
    }

    return $desc;
  }

  /**
   * Displays a number field for a settings field
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackNumber(array $args)
  {
    $value       = esc_attr($this->getOption($args['id'], $args['section'], $args['std']));
    $size        = (isset($args['size']) === true && $args['size'] !== null) ? $args['size'] : 'regular';
    $type        = (isset($args['type']) === true) ? $args['type'] : 'number';
    $placeholder = (empty($args['placeholder']) === true) ? '' : ' placeholder="' . $args['placeholder'] . '"';
    $min         = (empty($args['min']) === true) ? '' : ' min="' . $args['min'] . '"';
    $max         = (empty($args['max']) === true) ? '' : ' max="' . $args['max'] . '"';
    $step        = (empty($args['step']) === true) ? '' : ' step="' . $args['step'] . '"';

    $html = sprintf('<input type="%1$s" class="%2$s-number" id="%3$s[%4$s]" name="%3$s[%4$s]" value="%5$s" %6$s%7$s%8$s%9$s/>', $type, $size, $args['section'], $args['id'], $value, $placeholder, $min, $max, $step);
    $html .= $this->getFieldDescription($args);

    echo $html;
  }

  /**
   * Displays a checkbox for a settings field
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackCheckbox(array $args)
  {
    $value = esc_attr($this->getOption($args['id'], $args['section'], $args['std']));

    $html = '<fieldset>';
    $html .= sprintf('<label for="wpuf-%1$s[%2$s]">', $args['section'], $args['id']);
    $html .= sprintf('<input type="hidden" name="%1$s[%2$s]" value="off" />', $args['section'], $args['id']);
    $html .= sprintf('<input type="checkbox" class="checkbox" id="wpuf-%1$s[%2$s]" name="%1$s[%2$s]" value="1" %3$s />', $args['section'], $args['id'], checked($value, '1', false));
    $html .= sprintf('%1$s</label>', $args['desc']);
    $html .= '</fieldset>';

    echo $html;
  }

  /**
   * Displays a multicheckbox for a settings field
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackMulticheck(array $args)
  {
    $value = $this->getOption($args['id'], $args['section'], $args['std']);
    $html  = '<fieldset>';
    $html  .= sprintf('<input type="hidden" name="%1$s[%2$s]" value="" />', $args['section'], $args['id']);
    foreach ($args['options'] as $key => $label) {
      $checked = (isset($value[$key]) === true) ? $value[$key] : '0';
      $html    .= sprintf('<label for="wpuf-%1$s[%2$s][%3$s]">', $args['section'], $args['id'], $key);
      $html    .= sprintf('<input type="checkbox" class="checkbox" id="wpuf-%1$s[%2$s][%3$s]" name="%1$s[%2$s][%3$s]" value="%3$s" %4$s />', $args['section'], $args['id'], $key, checked($checked, $key, false));
      $html    .= sprintf('%1$s</label><br>', $label);
    }

    $html .= $this->getFieldDescription($args);
    $html .= '</fieldset>';

    echo $html;
  }

  /**
   * Displays a radio button for a settings field
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackRadio(array $args)
  {
    $value = $this->getOption($args['id'], $args['section'], $args['std']);
    $html  = '<fieldset>';

    foreach ($args['options'] as $key => $label) {
      $html .= sprintf('<label for="wpuf-%1$s[%2$s][%3$s]">', $args['section'], $args['id'], $key);
      $html .= sprintf('<input type="radio" class="radio" id="wpuf-%1$s[%2$s][%3$s]" name="%1$s[%2$s]" value="%3$s" %4$s />', $args['section'], $args['id'], $key, checked($value, $key, false));
      $html .= sprintf('%1$s</label><br>', $label);
    }

    $html .= $this->getFieldDescription($args);
    $html .= '</fieldset>';

    echo $html;
  }

  /**
   * Displays a selectbox for a settings field
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackSelect(array $args)
  {
    $value = esc_attr($this->getOption($args['id'], $args['section'], $args['std']));
    $size  = (isset($args['size']) === true && $args['size'] !== null) ? $args['size'] : 'regular';
    $html  = sprintf('<select class="%1$s" name="%2$s[%3$s]" id="%2$s[%3$s]">', $size, $args['section'], $args['id']);

    foreach ($args['options'] as $key => $label) {
      $html .= sprintf('<option value="%s" %s>%s</option>', $key, selected($value, $key, false), $label);
    }

    $html .= sprintf('</select>');
    $html .= $this->getFieldDescription($args);

    echo $html;
  }

  /**
   * Displays a textarea for a settings field
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackTextarea(array $args)
  {
    $value       = esc_textarea($this->getOption($args['id'], $args['section'], $args['std']));
    $size        = (isset($args['size']) === true && $args['size'] !== null) ? $args['size'] : 'regular';
    $placeholder = (empty($args['placeholder']) === true) ? '' : ' placeholder="' . $args['placeholder'] . '"';

    $html = sprintf('<textarea rows="5" cols="55" class="%1$s-text" id="%2$s[%3$s]" name="%2$s[%3$s]" %4$s>%5$s</textarea>', $size, $args['section'], $args['id'], $placeholder, $value);
    $html .= $this->getFieldDescription($args);

    echo $html;
  }

  /**
   * Displays the html for a settings field
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackHtml(array $args)
  {
    echo $this->getFieldDescription($args);
  }

  /**
   * Displays a rich text textarea for a settings field
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackWysiwyg(array $args)
  {
    $value = $this->getOption($args['id'], $args['section'], $args['std']);
    $size  = (isset($args['size']) === true && $args['size'] !== null) ? $args['size'] : '500px';

    echo '<div style="max-width: ' . $size . ';">';

    $editorSettings = [
      'teeny'         => true,
      'textarea_name' => $args['section'] . '[' . $args['id'] . ']',
      'textarea_rows' => 10,
    ];

    if (isset($args['options']) === true && is_array($args['options']) === true) {
      $editorSettings = array_merge($editorSettings, $args['options']);
    }

    wp_editor($value, $args['section'] . '-' . $args['id'], $editorSettings);

    echo '</div>';

    echo $this->getFieldDescription($args);
  }

  /**
   * Displays a file upload field for a settings field
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackFile(array $args)
  {
    $value = esc_attr($this->getOption($args['id'], $args['section'], $args['std']));
    $size  = (isset($args['size']) === true && $args['size'] !== null) ? $args['size'] : 'regular';
    $id    = $args['section'] . '[' . $args['id'] . ']';
    $label = (isset($args['options']['button_label']) === true) ? $args['options']['button_label'] : __('Choose File');

    $html = sprintf('<input type="text" class="%1$s-text wpsa-url" id="%2$s" name="%2$s" value="%3$s"/>', $size, $id, $value);
    $html .= '<input type="button" class="button wpsa-browse" value="' . $label . '" />';
    $html .= $this->getFieldDescription($args);

    echo $html;
  }

  /**
   * Displays an image upload field with a preview
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackImage(array $args)
  {
    $value  = esc_attr($this->getOption($args['id'], $args['section'], $args['std']));
    $size   = (isset($args['size']) === true && $args['size'] !== null) ? $args['size'] : 'regular';
    $id     = $args['section'] . '[' . $args['id'] . ']';
    $label  = (isset($args['options']['button_label']) === true) ? $args['options']['button_label'] : __('Choose Image');
    $img    = wp_get_attachment_image_src($value);
    $imgUrl = ($img !== false) ? $img[0] : '';

    $html = sprintf('<input type="hidden" class="%1$s-text wpsa-image-id" id="%2$s" name="%2$s" value="%3$s"/>', $size, $id, $value);
    $html .= '<p class="wpsa-image-preview"><img style="max-width:300px" src="' . $imgUrl . '" /></p>';
    $html .= '<input type="button" class="button wpsa-image-browse" value="' . $label . '" />';
    $html .= '<input type="button" class="button wpsa-image-clear" value="' . _x('Remove featured image', 'page') . '" />';
    $html .= $this->getFieldDescription($args);

    echo $html;
  }

  /**
   * Displays a password field for a settings field
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackPassword(array $args)
  {
    $value = esc_attr($this->getOption($args['id'], $args['section'], $args['std']));
    $size  = (isset($args['size']) === true && $args['size'] !== null) ? $args['size'] : 'regular';

    $html = sprintf('<input type="password" class="%1$s-text" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s"/>', $size, $args['section'], $args['id'], $value);
    $html .= $this->getFieldDescription($args);

    echo $html;
  }

  /**
   * Displays a color picker field for a settings field
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackColor(array $args)
  {
    $value = esc_attr($this->getOption($args['id'], $args['section'], $args['std']));
    $size  = (isset($args['size']) === true && $args['size'] !== null) ? $args['size'] : 'regular';

    $html = sprintf('<input type="text" class="%1$s-text wp-color-picker-field" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s" data-default-color="%5$s" />', $size, $args['section'], $args['id'], $value, $args['std']);
    $html .= $this->getFieldDescription($args);

    echo $html;
  }

  /**
   * Displays a select box for creating the pages select box
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  public function callbackPages(array $args)
  {
    $dropdownArgs = [
      'selected' => esc_attr($this->getOption($args['id'], $args['section'], $args['std'])),
      'name'     => $args['section'] . '[' . $args['id'] . ']',
      'id'       => $args['section'] . '[' . $args['id'] . ']',
      'echo'     => 0,
    ];
    $html         = wp_dropdown_pages($dropdownArgs);
    echo $html;
  }

  /**
   * Displays a visual divisor element.
   *
   * @param array $args Settings field args.
   *
   * @return void
   */
  function callbackDivisor($args)
  {
    echo "<hr>";
  }

  /* 
		o arg 'size' serve como limit.
    	passar array com id e nome.
		*/
  function callbackReorder($args)
  {

    $layout                = isset($args['layout']) ? $args['layout'] : 'vertical';
    $valor                 = esc_attr($this->getOption($args['id'], $args['section'], $args['std']));
    $css_display           = $layout == 'vertical' ? 'block' : 'inline';
    $fields_order_default  = $args['options'];

    if (!empty($valor)) {
      $ordem_gravada = explode(",", $valor);

      //ordena uma array baseada em outra
      uksort($fields_order_default, function ($key1, $key2) use ($ordem_gravada) {
        return (array_search($key1, $ordem_gravada) > array_search($key2, $ordem_gravada));
      });
    }

    ?>

      <div class="admin-module itens_reorder <?php echo $layout; ?>">

        <label><?php echo $args['desc']; ?></label>

        <?php
        $filter_fields_order = esc_attr($this->getOption($args['id'], $args['section'], $args['std']));

        $imploded  = $filter_fields_order;
        $items = is_array($filter_fields_order) ? $filter_fields_order : $fields_order_default;
        ?>

        <ul class="filter-fields-list">
          <input type='hidden' id='<?php echo $args['section']; ?>[<?php echo $args['id'] ?>]' value='<?php echo $imploded; ?>' name='<?php echo $args['section']; ?>[<?php echo $args['id'] ?>]' class='resultado_reorder' id='resultado_reorder' />
          <?php
          $pilha = 1;
          foreach ($items as $id => $nome) {
            if (isset($args['size']) && $i > $args['size']) continue; ?>
            <li class="sortable-item " style='display: <?php echo $css_display; ?>; margin-right: 3px;  background-color: #e5e5e5; width: 250px; overflow: hidden; padding: 7px; height: 36px'>
              <!-- display: inline-block; -->
              <?php echo $nome; ?>
              <input type="hidden" name="<?php echo $id; ?>" value="<?php echo $pilha; ?>" />
            </li>
            <?php $pilha++;
          } ?>
        </ul>
      </div>

<?php
}

/**
 * Sanitize callback for Settings API
 *
 * @param mixed $options The options.
 *
 * @return mixed
 */
public function sanitizeOptions($options)
{
  if (is_array($options) === false) {
    return $options;
  }
  foreach ($options as $optionSlug => $optionValue) {
    $sanitizeCallback = $this->getSanitizeCallback($optionSlug);

    // If callback is set, call it
    if ($sanitizeCallback !== false) {
      $options[$optionSlug] = call_user_func($sanitizeCallback, $optionValue);
      continue;
    }
  }

  return $options;
}

/**
 * Get sanitization callback for given option slug
 *
 * @param string $slug Option slug.
 *
 * @return mixed string or bool false
 */
public function getSanitizeCallback(string $slug = '')
{
  if (empty($slug) === true) {
    return false;
  }

  // Iterate over registered fields and see if we can find proper callback
  foreach ($this->settingsFields as $options) {
    foreach ($options as $option) {
      if ($option['name'] !== $slug) {
        continue;
      }

      // Return the callback name
      return (isset($option['sanitize_callback']) === true && is_callable($option['sanitize_callback']) === true) ? $option['sanitize_callback'] : false;
    }
  }

  return false;
}

/**
 * Show navigations as tab
 * Shows all the settings section labels as tab
 *
 * @return void
 */
public function showNavigation()
{
  $html = '<h2 class="nav-tab-wrapper">';

  $count = count($this->settingsSections);

  // don't show the navigation if only one section exists
  if ($count === 1) {
    return;
  }

  foreach ($this->settingsSections as $tab) {
    $html .= sprintf('<a href="#%1$s" class="nav-tab" id="%1$s-tab">%2$s</a>', $tab['id'], $tab['title']);
  }

  $html .= '</h2>';

  echo $html;
}

/**
 * Show the section settings forms
 *
 * This function displays every sections in a different form
 *
 * @return void
 */
public function showForms()
{
  ?>
  <div class="metabox-holder">
    <?php foreach ($this->settingsSections as $form) { ?>
      <div id="<?php echo $form['id']; ?>" class="group" style="display: none;">
        <form method="post" action="options.php">
          <?php
          do_action('wsa_form_top_' . $form['id'], $form);
          settings_fields($form['id']);
          do_settings_sections($form['id']);
          do_action('wsa_form_bottom_' . $form['id'], $form);
          if (isset($this->settingsFields[$form['id']]) === true) {
            ?>
            <div style="padding-left: 10px">
              <?php submit_button(); ?>
            </div>
          <?php
        }
        ?>
        </form>
      </div>
    <?php } ?>
  </div>
  <?php
  $this->script();
}

/**
 * Tabbable JavaScript codes & Initiate Color Picker
 * This code uses localstorage for displaying active tabs
 *
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @return void
 */
public function script()
{
  ?>
  <script>
    jQuery(document).ready(function($) {
      //Initiate Color Picker
      $('.wp-color-picker-field').wpColorPicker();

      // Switches option sections
      $('.group').hide();
      var activetab = '';
      if (typeof localStorage !== 'undefined') {
        activetab = localStorage.getItem("activetab");
      }

      //if url has section id as hash then set it as active or override the current local storage value
      if (window.location.hash) {
        activetab = window.location.hash;
        if (typeof localStorage !== 'undefined') {
          localStorage.setItem("activetab", activetab);
        }
      }

      if (activetab !== '' && $(activetab).length) {
        $(activetab).fadeIn();
      } else {
        $('.group:first').fadeIn();
      }
      $('.group .collapsed').each(function() {
        $(this).find('input:checked').parent().parent().parent().nextAll().each(
          function() {
            if ($(this).hasClass('last')) {
              $(this).removeClass('hidden');
              return false;
            }
            $(this).filter('.hidden').removeClass('hidden');
          });
      });

      if (activetab !== '' && $(activetab + '-tab').length) {
        $(activetab + '-tab').addClass('nav-tab-active');
      } else {
        $('.nav-tab-wrapper a:first').addClass('nav-tab-active');
      }
      $('.nav-tab-wrapper a').on('click', function(evt) {
        $('.nav-tab-wrapper a').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active').blur();
        var clicked_group = $(this).attr('href');
        if (typeof localStorage !== 'undefined') {
          localStorage.setItem("activetab", $(this).attr('href'));
        }
        $('.group').hide();
        $(clicked_group).fadeIn();
        evt.preventDefault();
      });

      $('.wpsa-browse').on('click', function(event) {
        event.preventDefault();

        var self = $(this);

        // Create the media frame.
        var file_frame = wp.media.frames.file_frame = wp.media({
          title: self.data('uploader_title'),
          button: {
            text: self.data('uploader_button_text')
          },
          multiple: false
        }).on('select', function() {
          var attachment = file_frame.state().get('selection').first().toJSON();
          self.prev('.wpsa-url').val(attachment.url).change();
        }).open();
      });
      $('.wpsa-image-browse').on('click', function(event) {
        event.preventDefault();
        var self = $(this);

        // Create the media frame.
        var file_frame = wp.media.frames.file_frame =
          wp.media({
            title: self.data('uploader_title'),
            button: {
              text: self.data('uploader_button_text')
            },
            multiple: false,
            library: {
              type: 'image'
            }
          }).on('select', function() {
            var attachment = file_frame.state().get('selection').first().toJSON();
            var url;
            if (attachment.sizes && attachment.sizes.thumbnail) {
              url = attachment.sizes.thumbnail.url;
            } else {
              url = attachment.url;
            }
            self.parent().children('.wpsa-image-id').val(attachment.id).change();
            self.parent().children('.wpsa-image-preview').children('img').attr('src', url);
          }).open();
      });
      $('.wpsa-image-clear').on('click', function(event) {
        event.preventDefault();
        var self = $(this);

        self.parent().children('.wpsa-image-id').val('').change();
        self.parent().children('.wpsa-image-preview').children('img').attr('src', '');
      });
    });
  </script>
<?php
}
}
