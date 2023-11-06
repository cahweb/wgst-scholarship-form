<?php

namespace CAH\WGST;

require_once "class.formInput.php";

class FileInput extends FormInput
{
    private $isMulti;
    private $accept;
    private $colWidth;

    public function __construct(
        string $name,
        string $label,
        bool $isMulti = false,
        string $accept = "",
        bool $isRequired = false,
        string $formText = null,
        array $additionalAttrs = null,
        int $baseWidth = 12
    ) {
        parent::__construct($name, 'file', $label, null, null, $isRequired, $formText, $additionalAttrs, $baseWidth);

        $this->isMulti = $isMulti;
        $this->accept = $accept;
        $this->colWidth = $this->baseWidth > 6 ? $this->baseWidth / 2 : $this->baseWidth;
    }

    public function __toString()
    {
        ob_start();
        ?>
        <p class="form-text"><?= $this->label ?></p>
        <?php if ($this->baseWidth > 6) : ?>
        <div class="row mb-3">
        <?php endif; ?>

            <div class="col-md-<?= $this->colWidth ?> form-group">
                <label class="custom-file">
                    <input type="<?= $this->type ?>" id="<?= $this->name ?>" name="<?= $this->name ?>" class="custom-file-input"<?= !empty($this->accept) ? " accept=\"{$this->accept}\"" : "" ?><?= $this->isMulti ? " multiple" : "" ?><?= $this->isRequired ? " required" : "" ?>>
                    <span class="custom-file-control" data-after="Choose file..."></span>
                </label>
            </div>

        <?php if ($this->baseWidth > 6) : ?>
        </div>
        <?php endif; ?>
        <?php
        $output = ob_get_clean();

        return $output;
    }
}