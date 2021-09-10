<?php

namespace App\Services\Mailer;

use App\Entity\User;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailHandler
{

    private MailerInterface $mailer;

    /**
     * @param MailerInterface $mailer
     */
    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendConfirmEmail(String $clientUrl, User $user)
    {
        //todo pour to remetre email de l'user
   //     $confirmLink = $_ENV['APP_DNS']."/user/activation?token=".$user->getActivationToken();
        //for classic url params of react needed format
        if(substr($clientUrl, -1) === "/"){//for react
            $confirmLink = $clientUrl.$user->getActivationToken();
        }else{//classic
            $confirmLink = $clientUrl."?token=".$user->getActivationToken();
        }

      //  $token = $user->getActivationToken();
      //  $confirmLink = $_ENV['APP_DNS']."/user/activation";

        $email = (new Email())
            ->from("kaylah.heathcote@ethereal.email")
            ->to("kaylah.heathcote@ethereal.email")
            //->cc('cc@example.com')
            //->bcc('bcc@example.com')
            //->replyTo('fabien@example.com')
            //->priority(Email::PRIORITY_HIGH)
            ->subject('Confirm your account')
            ->text('Sending emails is fun again!')
            ->html('<a href='.$confirmLink. '> clic for confirm you registration </a>'
            );

        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendEmail()
    {
        $email = (new Email())
            ->from('hello@example.com')
            ->to('you@example.com')
            //->cc('cc@example.com')
            //->bcc('bcc@example.com')
            //->replyTo('fabien@example.com')
            //->priority(Email::PRIORITY_HIGH)
            ->subject('Time for Symfony Mailer!')
            ->text('Sending emails is fun again!')
            ->html('<p>See Twig integration for better HTML integration!</p>');

        $this->mailer->send($email);
    }
}