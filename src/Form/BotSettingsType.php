<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Form;

use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The texts a page shows (greeting and menu labels), the sales channel it
 * sells from, and instructions for an AI reply generator.
 */
final class BotSettingsType extends AbstractType
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(private readonly ChannelRepositoryInterface $channelRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $channels = [];
        /** @var ChannelInterface $channel */
        foreach ($this->channelRepository->findBy(['enabled' => true]) as $channel) {
            $channels[(string) $channel->getName()] = (string) $channel->getCode();
        }

        $menuLabel = static fn (string $label): array => [
            'label' => $label,
            'constraints' => [new NotBlank(), new Length(max: 30)],
            'attr' => ['maxlength' => 30],
        ];

        $builder
            ->add('greeting', TextType::class, [
                'label' => 'sylius_bot.ui.settings.greeting',
                'help' => 'sylius_bot.ui.settings.greeting_help',
                'constraints' => [new NotBlank(), new Length(max: 160)],
                'attr' => ['maxlength' => 160],
            ])
            ->add('list_products', TextType::class, $menuLabel('sylius_bot.ui.settings.list_products'))
            ->add('my_cart', TextType::class, $menuLabel('sylius_bot.ui.settings.my_cart'))
            ->add('empty_cart', TextType::class, $menuLabel('sylius_bot.ui.settings.empty_cart'))
            ->add('checkout', TextType::class, $menuLabel('sylius_bot.ui.settings.checkout'))
            ->add('channel_code', ChoiceType::class, [
                'label' => 'sylius_bot.ui.settings.channel',
                'help' => 'sylius_bot.ui.settings.channel_help',
                'choices' => $channels,
                'required' => false,
                'placeholder' => 'sylius_bot.ui.settings.channel_default',
            ])
            ->add('ai_instructions', TextareaType::class, [
                'label' => 'sylius_bot.ui.settings.ai_instructions',
                'help' => 'sylius_bot.ui.settings.ai_instructions_help',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'messages']);
    }
}
