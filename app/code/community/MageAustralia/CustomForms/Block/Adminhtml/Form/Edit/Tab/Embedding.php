<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MageAustralia_CustomForms_Block_Adminhtml_Form_Edit_Tab_Embedding extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm(): self
    {
        /** @var MageAustralia_CustomForms_Model_Form $model */
        $model = Mage::registry('customforms_form');
        $helper = Mage::helper('customforms');
        $code = (string) $model->getCode();

        $form = new Maho\Data\Form();
        $fs = $form->addFieldset('embedding', ['legend' => $this->__('Embedding')]);
        $fs->addField('embed_snippet', 'note', [
            'label' => $this->__('CMS snippet'),
            'text'  => $this->_embedSnippetHtml($helper->getCmsSnippet($code !== '' ? $code : 'YOUR_CODE')),
        ]);
        $fs->addField('embed_layout', 'note', [
            'label' => $this->__('Layout XML'),
            'text'  => '<code class="cf-embed__code">' . $this->escapeHtml($helper->getLayoutSnippet($code !== '' ? $code : 'YOUR_CODE')) . '</code>',
        ]);
        $this->setForm($form);
        return parent::_prepareForm();
    }

    private function _embedSnippetHtml(string $snippet): string
    {
        $esc = $this->escapeHtml($snippet);
        return '<div class="cf-embed">'
            . '<code id="cf-embed-snippet" class="cf-embed__code">' . $esc . '</code> '
            . '<button type="button" class="scalable" onclick="customFormsCopyEmbed(this)">'
            . '<span>' . $this->escapeHtml($this->__('Copy')) . '</span></button>'
            . '<p class="note">' . $this->escapeHtml($this->__('Paste into any CMS page or static block to render this form.')) . '</p>'
            . '</div>'
            . '<script type="text/javascript">//<![CDATA[' . "\n"
            . 'function customFormsCopyEmbed(btn){'
            . '  var t=document.getElementById("cf-embed-snippet").textContent;'
            . '  if(navigator.clipboard){navigator.clipboard.writeText(t);}'
            . '  var s=btn.querySelector("span");if(s){var o=s.textContent;s.textContent="Copied";setTimeout(function(){s.textContent=o;},1200);}'
            . '}'
            . "\n//]]></script>";
    }

    #[\Override]
    public function getTabLabel(): string
    {
        return $this->__('Embedding');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return $this->__('Embedding');
    }

    #[\Override]
    public function canShowTab(): bool
    {
        return true;
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }
}
