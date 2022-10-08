<?php


namespace SyliusBotPlugin\Form;

use SyliusBotPlugin\Entity\Bot;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class PersistentMenuFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add("page", EntityType::class, [
                "required" => true,
                "mapped" => false,
                "class" => Bot::class,
                "choice_label" => 'page_name',
                "attr" => [
                    "class" => "required field"
                ],
            ])
            ->add("bot_id", HiddenType::class)
            ->add("facebook_page", HiddenType::class)
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
            ->add("get_started_text", TextType::class, [
                "required" => true,
                "attr" => [
                    "class" => "required field"
                ],
            ])
        ;

    }
}
