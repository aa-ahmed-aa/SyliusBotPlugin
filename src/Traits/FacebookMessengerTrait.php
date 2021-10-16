<?php


namespace Ahmedkhd\SyliusBotPlugin\Traits;


use BotMan\Drivers\Facebook\Extensions\ButtonTemplate;
use BotMan\Drivers\Facebook\Extensions\Element;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
use BotMan\Drivers\Facebook\Extensions\GenericTemplate;
use BotMan\Drivers\Facebook\Extensions\ReceiptAdjustment;
use BotMan\Drivers\Facebook\Extensions\ReceiptElement;
use BotMan\Drivers\Facebook\Extensions\ReceiptSummary;
use BotMan\Drivers\Facebook\Extensions\ReceiptTemplate;

trait FacebookMessengerTrait
{
    /**
     * @param string $type
     * @param array $elements
     * @return array
     */
    public function createCarosel($type = GenericTemplate::RATIO_SQUARE, $elements = [])
    {
        $message = GenericTemplate::create()
            ->addImageAspectRatio($type)
            ->addElements($elements)
            ->toArray()
        ;

        unset($message['quick_replies']);

        return $message;
    }

    /**
     * @param string $name
     * @param string $type
     * @param string $payload
     * @param string $url
     * @return ElementButton|string
     */
    public function createButton($name = "", $type = "postback", $payload = "", $url = "")
    {
        $button = '';
        switch ($type)
        {
            case "postback":
                $button = ElementButton::create($name)
                    ->type($type)
                    ->payload($payload);
                break;
            case "url":
                $button = ElementButton::create($name)
                    ->url($url);
                break;
        }

        return $button;
    }

    /**
     * @param string $name
     * @param string $subtitle
     * @param string $image
     * @param array $buttons
     * @return Element
     */
    public function createCaroselCard($name = "", $subtitle = "", $image = "", $buttons = [])
    {
        return Element::create($name)
            ->subtitle($subtitle)
            ->image($image)
            ->addButtons($buttons);
    }

    /**
     * @param string $actionButtonText
     * @param string $actionButtonUrl
     * @param array $elements
     * @return ReceiptTemplate
     */
    public function createReceiptTemplate(string $actionButtonText, string $actionButtonUrl, array $elements)
    {
        $items = $this->createReceiptElements($elements);
        return ReceiptTemplate::create()
            ->recipientName($actionButtonText)
            ->merchantName($this->getDefaultChannel()->getName())
            ->orderNumber($this->getOrder()->getId())
            ->timestamp($this->getOrder()->getCreatedAt()->getTimestamp())
            ->orderUrl($actionButtonUrl)
            ->paymentMethod('Visa | Bank Transfer | Cash on delivery')
            ->currency($this->getDefaultChannel()->getBaseCurrency()->getCode())
            ->addElements($items)
            ->addSummary(ReceiptSummary::create()
                ->subtotal($this->getOrder()->getItemsTotal() * 10)
                ->shippingCost($this->getOrder()->getShippingTotal() *10)
                ->totalTax($this->getOrder()->getTaxTotal() * 10)
                ->totalCost($this->getOrder()->getTotal() * 10)
            )
            ->addAdjustment(ReceiptAdjustment::create('Adjustment')
                ->amount($this->getOrder()->getAdjustmentsTotal() / 100)
            );
    }

    /**
     * @param array $elements
     * @return array
     */
    public function createReceiptElements(array $elements)
    {
        $items = [];
        foreach ($elements as $element)
        {
            $items[] = ReceiptElement::create($element["title"])
                ->price((double)$element["description"])
                ->quantity($element["quantity"])
                ->currency($this->getDefaultChannel()->getBaseCurrency()->getCode())
                ->image($element["image"]);
        }

        return $items;
    }

    /**
     * @param string $text
     * @param array $buttons
     * @return ButtonTemplate
     */
    public function createButtonTemplate($text = "", $buttons = [])
    {
        return ButtonTemplate::create($text)
            ->addButtons($buttons);
    }
}
