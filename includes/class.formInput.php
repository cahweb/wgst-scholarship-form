<?php
namespace CAH\WGST;

class FormInput
{
    /**
     * @var string
     *
     * The contents of the input's `name` attribute.
     */
    protected $name;

    /**
     * @var string
     *
     * The human readable text for the `label` element.
     */
    protected $label;

    /**
     * @var string
     *
     * The type of the input, for the `type` attribute.
     */
    protected $type;

    /**
     * @var int|string|bool|array
     *
     * The current value of the input, if any
     */
    protected $value;

    /**
     * @var array|callable
     *
     * Either an array of options for a `select` element, or a
     * callable function that will return such an array.
     */
    protected $options;

    /**
     * @var bool
     *
     * Whether the input should be required or not. Default `false`.
     */
    protected $isRequired;

    /**
     * @var string
     *
     * Any additional text to appear beneath the `label`, like a tooltip.
     */
    protected $formText;

    /**
     * @var array
     *
     * An array of additional attributes, which will be appended to the `input`
     * after the other named member variable values.
     */
    protected $additionalAttrs;

    /**
     * @var int
     *
     * The default width of the input, in Bootstrap grid units.
     */
    protected $baseWidth;

    /**
     * Constructor
     *
     * Creates a new FormInput object, which can generate appropriate HTML output
     * from rudimentary JSON data. Used primarily as syntactic sugar for cleaner
     * operating code.
     *
     * @param string $name             The name attribute for the input
     * @param string $type             The type of input, so we know how to print it
     * @param string $label            The formatted label text
     * @param mixed $value             The curent value of the input, if any
     * @param array|callable $options  For select elements only; an array of 
     *                                 options or a string representing a 
     *                                 callable function in the form's namespace
     * @param string $formText         Any additional text to show near the input
     * @param array $additionalAttrs   Any additional attributes, as an array of
     *                                 key/value pairs
     * @param int $baseWidth           The initial width of the input, in 
     *                                  Bootstrap grid units
     *
     * @return void
     */
    public function __construct(
        string $name,
        string $type,
        string $label,
        $value,
        $options = null,
        bool $isRequired = false,
        string $formText = null,
        array $additionalAttrs = null,
        int $baseWidth = 12
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->label = $label;
        $this->value = $value;
        $this->isRequired = $isRequired;

        // Looping through like this because DRYer is better
        foreach (['options', 'formText', 'additionalAttrs', 'baseWidth'] as $attr) {
            if (!is_null($$attr)) {
                $this->$attr = $$attr;
            }
        }
    }

    /**
     * The __toString magic method implementation
     *
     * Used so we can just call `echo $obj;` and it'll print itself appropriately.
     *
     * @return string
     */
    public function __toString()
    {
        $output = "";

        // Just pass the work off to a private secondary function, for ease of
        // organization
        switch ($this->type) {
            case 'select':
                $output = $this->selectInput();
                break;

            case 'textarea':
                $output = $this->textareaInput();
                break;

            default:
                $output = $this->basicInput();
                break;
        }

        if ($this->baseWidth > 6) {
            $output = '<div class="row">' . $output . '</div>';
        }

        return $output;
    }

    /**
     * Standard Input HTML Generator
     *
     * Generates HTML conforming to any of the standard HTML5 `input` element types.
     *
     * @return string
     */
    private function basicInput() : string
    {
        // Parse the additional attributes so we can add them to the input
        $additionalAttrs = $this->parseAdditionalAttrs();

        $colWidth = $this->baseWidth > 6 ? $this->baseWidth / 2 : $this->baseWidth;

        // I like using output buffers so I can just write HTML
        ob_start();
        ?>
        <div class="form-group col-md-<?= $colWidth ?> mb-3 pe-2">
            <label for="<?= $this->name ?>" class="form-label"><?= $this->label ?></label>
            <?php $this->maybePrintFormText(); ?>
            <input type="<?= $this->type ?>" id="<?= $this->name ?>" name="<?= $this->name ?>" class="form-control"<?= !empty($this->value) ? "value=\"{$this->value}\"" : "" ?><?= !empty($additionalAttrs) ? " $additionalAttrs" : "" ?><?= $this->formText ? " aria-describedby=\"form-text-{$this->name}\"" : "" ?><?= $this->isRequired ? " required" : "" ?>>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Select Input Generator
     *
     * Generates HTML for an HTML5 `select` element.
     *
     * @return string
     */
    private function selectInput() : string
    {
        // Either cache the array of options or call the passed function
        // to generate them.
        $options = [];
        if (!is_array($this->options)) {
            try {
                $options = call_user_func(__NAMESPACE__ . "\\" . $this->options);
            } catch (\Exception $e) {
                error_log("[" . date(DATE_RFC3339) . "] $e");
            }
        } else {
            $options = $this->options;
        }

        $additionalAttrs = $this->parseAdditionalAttrs();

        $colWidth = $this->baseWidth > 6 ? $this->baseWidth / 2 : $this->baseWidth;

        ob_start();
        ?>
        <div class="form-group col-md-<?= $colWidth ?> mb-3 pe-2">
            <label for="<?= $this->name ?>" class="form-label"><?= $this->label ?></label>
            <?php $this->maybePrintFormText(); ?>
            <select id="<?= $this->name ?>" name="<?= $this->name ?>" class="form-control"<?= !empty($additionalAttrs) ? " $additionalAttrs" : "" ?><?= $this->formText ? " aria-describedby=\"form-text-{$this->name}\"" : "" ?><?= $this->isRequired ? " required" : "" ?>>
            <?php if (is_array($this->options) && $this->options[0] != "no-default") : ?>
                <option value="">-- Please Select -- </option>
            <?php endif; ?>
            <?php foreach ($options as $option) : ?>
                <?php 
                if (!is_array($option) && $option == "no-default") {
                    continue;
                }

                $value = "";
                $label = "";

                if (is_array($option)) {
                    $key = array_keys($option)[0];
                    $value = $option[$key];
                    $label = is_string($key) ? ucfirst($key) : $key;
                } else {
                    $value = $option;
                    $label = is_string($option) ? ucfirst($option) : $option;
                }
                ?>
                <option value="<?= $value ?>"<?= isset($this->value) && $this->value == $value ? " selected" : "" ?>><?= $label ?></option>
            <?php endforeach; ?>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }

    private function textareaInput() : string
    {
        foreach (['rows' => 5, 'cols' => 125] as $textAreaAttr => $defaultValue) {
            if (!isset($this->additionalAttrs[$textAreaAttr])) {
                $this->additionalAttrs[$textAreaAttr] = $defaultValue;
            }
        }

        $additionalAttrs = $this->parseAdditionalAttrs();

        ob_start();
        ?>
        <div class="form-group col-md-<?= $this->baseWidth ?> mb-3">
            <label for="<?= $this->name ?>" class="form-label"><?= $this->label ?></label>
            <?php $this->maybePrintFormText(); ?>
            <textarea id="<?= $this->name ?>" name="<?= $this->name ?>" class="form-control"<?= !empty($additionalAttrs) ? " $additionalAttrs" : "" ?><?= $this->formText ? " aria-describedby=\"form-text-{$this->name}\"" : "" ?><?= $this->isRequired ? " required" : "" ?>><?= !empty($this->value) ? $this->value : "" ?></textarea>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Stringifies the `FormInput::$additionalAttrs` member variable
     *
     * Parses any key/value pairs from the `FormInput::$additionalAttrs` member
     * variable into a string containing a series of HTML-formatted attributes.
     *
     * @return string
     */
    private function parseAdditionalAttrs() : string
    {
        $attrStr = "";
        if ($this->additionalAttrs) {
            $attrs = [];
            foreach ($this->additionalAttrs as $key => $value) {
                if ($key == "required" && $value) {
                    $attrs[] = "required";
                } else {
                    $attrs[] = "$key=\"$value\"";
                }
            }
            $attrStr = !empty($attrs) ? implode(" ", $attrs) : "";
        }
        return $attrStr;
    }

    private function maybePrintFormText()
    {
        if (!is_null($this->formText) && !empty($this->formText)) {
            ob_start();
            ?>
            <p class="form-text" id="form-text-<?= $this->name ?>"><?= $this->formText ?></p>
            <?php
            echo ob_get_clean();
        }
    }
}
