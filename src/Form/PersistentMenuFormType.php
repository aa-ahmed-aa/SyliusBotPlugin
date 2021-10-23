<?php


namespace Ahmedkhd\SyliusBotPlugin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class PersistentMenuFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add("list_products", TextType::class, [
                "required" => true,
                "attr" => [
                    "class" => "required field"
                ],
            ])
            ->add("order_summery", TextType::class, [
                "required" => true,
                "attr" => [
                    "class" => "required field"
                ],
            ])
            ->add("my_cart", TextType::class, [
                "required" => true,
                "attr" => [
                    "class" => "required field"
                ],
            ])
            ->add("empty_cart", TextType::class, [
                "required" => true,
                "attr" => [
                    "class" => "required field"
                ],
            ])
            ->add("checkout", TextType::class, [
                "required" => true,
                "attr" => [
                    "class" => "required field"
                ],
            ])
        ;

    }
}
