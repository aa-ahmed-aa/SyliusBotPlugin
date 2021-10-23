<?php

declare(strict_types=1);

namespace Ahmedkhd\SyliusBotPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="ahmedkhd_sylius_bot_subscriber")
 */
class BotSubscriber implements BotSubscriberInterface
{
    use TimestampableEntity;

    /**
     * @var integer | null
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string | null
     * @ORM\Column(name="name", type="string", length=255, nullable=true)
     */
    public $name;

    /**
     * @var string | null
     * @ORM\Column(name="bot_subscriber_id", type="string", length=255, unique=true, nullable=false)
     */
    public $botSubscriberId;

    /**
     * @var string | null
     * @ORM\Column(name="channel", type="string", length=255, nullable=false)
     */
    public $channel;

    /**
     * @var string | null
     * @ORM\Column(name="first_name", type="string", length=255, nullable=true)
     */
    public $firstName;

    /**
     * @var string | null
     * @ORM\Column(name="last_name", type="string", length=255, nullable=true)
     */
    public $lastName;

    /**
     * @var string | null
     * @ORM\Column(name="profile_picture", type="string", length=255, nullable=true)
     */
    public $profilePicture;

    /**
     * @var string | null
     * @ORM\Column(name="locale", type="string", length=255, nullable=true)
     */
    public $locale;

    /**
     * @var string | null
     * @ORM\Column(name="timezone", type="string", length=255, nullable=true)
     */
    public $timezone;

    /**
     * @var string | null
     * @ORM\Column(name="gender", type="string", length=255, nullable=true)
     */
    public $gender;

    /**
     * @var CustomerInterface
     * @ORM\OneToOne(targetEntity="Sylius\Component\Core\Model\Customer")
     * @ORM\JoinColumn(name="customer_id", referencedColumnName="id")
     */
    public $customer;

    public function __construct()
    {
        $this->customer = new Customer();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string|null $name
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $firstName
     */
    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    /**
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * @param string|null $lastName
     */
    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    /**
     * @return string|null
     */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * @param string|null $timezone
     */
    public function setTimezone(?string $timezone): void
    {
        $this->timezone = $timezone;
    }

    /**
     * @return string|null
     */
    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * @param string|null $profilePicture
     */
    public function setProfilePicture(?string $profilePicture): void
    {
        $this->profilePicture = $profilePicture;
    }

    /**
     * @return string|null
     */
    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    /**
     * @param string|null $locale
     */
    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * @return string|null
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * @param string|null $gender
     */
    public function setGender(?string $gender): void
    {
        $this->gender = $gender;
    }

    /**
     * @return string|null
     */
    public function getGender(): ?string
    {
        return $this->gender;
    }

    /**
     * @return string|null
     */
    public function getBotSubscriberId(): ?string
    {
        return $this->botSubscriberId;
    }

    /**
     * @param string|null $botSubscriberId
     */
    public function setBotSubscriberId(?string $botSubscriberId): void
    {
        $this->botSubscriberId = $botSubscriberId;
    }

    /**
     * @param string|null $channel
     */
    public function setChannel(?string $channel): void
    {
        $this->channel = $channel;
    }

    /**
     * @return string|null
     */
    public function getChannel(): ?string
    {
        return $this->channel;
    }

    /**
     * @param CustomerInterface $customer
     */
    public function setCustomer(CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    /**
     * @return CustomerInterface
     */
    public function getCustomer(): CustomerInterface
    {
        return $this->customer;
    }
}
