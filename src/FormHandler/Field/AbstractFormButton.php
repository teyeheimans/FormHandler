<?php
namespace FormHandler\Field;

abstract class AbstractFormButton extends Element
{
    /**
     * The form object where this image button is located in.
     * @var Form
     */
    protected $form;

    /**
     * Is this button disabled?
     * @var bool
     */
    protected $disabled = false;

    /**
     * The name of the button
     * @var string
     */
    protected $name;

    /**
     * The size of the button
     * @var int
     */
    protected $size;

    /**
     * Return the form instance of this field
     *
     * @return Form
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Set if this field is disabled and return the ImageButton reference
     *
     * @param bool $disabled
     * @return self
     */
    public function setDisabled($disabled)
    {
        $this->disabled = $disabled;
        return $this;
    }

    /**
     * Return if this field is disabled
     *
     * @return bool
     */
    public function isDisabled()
    {
        return $this->disabled;
    }

    /**
     * Set the name of the field and return the ImageButton reference
     *
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Return the name of the ImageButton
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the size of the field and return the ImageButton reference
     *
     * @param int $size
     * @return self
     */
    public function setSize($size)
    {
        $this->size = $size;
        return $this;
    }

    /**
     * Return the size of the field
     *
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }
}