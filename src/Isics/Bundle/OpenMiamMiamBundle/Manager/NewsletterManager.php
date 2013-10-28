<?php

/*
 * This file is part of the OpenMiamMiam project.
 *
 * (c) Isics <contact@isics.fr>
 *
 * This source file is subject to the AGPL v3 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Isics\Bundle\OpenMiamMiamBundle\Manager;

use Doctrine\ORM\EntityManager;
use Isics\Bundle\OpenMiamMiamBundle\Entity\Newsletter;
use Isics\Bundle\OpenMiamMiamBundle\Entity\Association;
use Isics\Bundle\OpenMiamMiamUserBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class NewsletterManager
 *
 * @package Isics\Bundle\OpenMiamMiamBundle\Manager
 */
class NewsletterManager
{
    /**
     * @var EntityManager $entityManager
     */
    protected $entityManager;

    /**
     * @var ActivityManager $activityManager
     */
    protected $activityManager;

    /**
     * @var \Swift_mailer
     */
    protected $mailer;

    /**
     * @var array $mailerConfig
     */
    protected $mailerConfig;

    /**
     * @var EngineInterface
     */
    protected $engine;

    /**
     * Constructs object
     *
     * @param EntityManager     $entityManager
     * @param ActivityManager   $activityManager
     * @param \Swift_Mailer     $mailer
     * @param array             $mailerConfig
     * @param EngineInterface   $engine
     */
    public function __construct(EntityManager $entityManager,
                                ActivityManager $activityManager,
                                \Swift_Mailer $mailer,
                                array $mailerConfig,
                                EngineInterface $engine)
    {
        $this->entityManager = $entityManager;
        $this->activityManager = $activityManager;
        $this->mailer = $mailer;
        $this->engine = $engine;

        $resolver = new OptionsResolver();
        $this->setMailerConfigResolverDefaultOptions($resolver);
        $this->mailerConfig = $resolver->resolve($mailerConfig);
    }

    /**
     * Set the defaults options
     *
     * @param OptionsResolverInterface $resolver
     */
    protected function setMailerConfigResolverDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setRequired(array('sender_name', 'sender_address'));
    }

    /**
     * Returns a new newsletter for an association
     *
     * @param Association $association
     *
     * @return Newsletter
     */
    public function createForAssociation(Association $association)
    {
        $newsletter = new Newsletter();
        $newsletter->setAssociation($association);

        $newsletter->setRecipientType(Newsletter::RECIPIENT_TYPE_ALL);

        // Select all association branches
        $newsletter->setBranches($association->getBranches());

        return $newsletter;
    }

    /**
     * Returns a new newsletter for super
     *
     * @return Newsletter
     */
    public function createForSuper()
    {
        $newsletter = new Newsletter();

        return $newsletter;
    }

    /**
     * Saves a newsletter
     *
     * @param Newsletter $newsletter
     * @param User       $user
     */
    public function save(Newsletter $newsletter, User $user = null)
    {
        $association = $newsletter->getAssociation();

        $activityTransKey = null;
        if (null === $newsletter->getId()) {
            $activityTransKey = 'activity_stream.newsletter.created';
        }
        else {
            $unitOfWork = $this->entityManager->getUnitOfWork();
            $unitOfWork->computeChangeSets();

            $changeSet = $unitOfWork->getEntityChangeSet($newsletter);
            if (!empty($changeSet)) {
                $activityTransKey = 'activity_stream.newsletter.updated';
            }
        }

        // Save object
        $this->entityManager->persist($newsletter);
        $this->entityManager->flush();

        // Activity
        if (null !== $activityTransKey) {
            $activity = $this->activityManager->createFromEntities(
                $activityTransKey,
                array('%title%' => $newsletter->getSubject()),
                $newsletter,
                $association,
                $user
            );
            $this->entityManager->persist($activity);
            $this->entityManager->flush();
        }
    }

    /**
     * Send newsletter to consumers and/or producers
     *
     * @param Newsletter $newsletter
     * @param User       $user
     *
     * @return integer Number of recipients
     */
    public function send(Newsletter $newsletter, $user)
    {
        $recipients    = array();
        $recipientType = $newsletter->getRecipientType();

        if ($recipientType === Newsletter::RECIPIENT_TYPE_CONSUMER || $recipientType === Newsletter::RECIPIENT_TYPE_ALL) {
            $consumers = $this->entityManager
                ->getRepository('IsicsOpenMiamMiamUserBundle:User')
                ->findConsumersForBranches($newsletter->getBranches());

            $recipients = array_merge($recipients, $consumers);
        }

        if ($recipientType === Newsletter::RECIPIENT_TYPE_PRODUCER || $recipientType === Newsletter::RECIPIENT_TYPE_ALL) {
            $producers = $this->entityManager
                ->getRepository('IsicsOpenMiamMiamUserBundle:User')
                ->findManagingProducer(
                    $this->entityManager
                        ->getRepository('IsicsOpenMiamMiamBundle:Producer')
                        ->findIdsForBranch($newsletter->getBranches())
                );

            $recipients = array_merge($recipients, $producers);
        }

        $recipients = array_unique($recipients);

        $nbRecipients = count($recipients);

        if (0 < $nbRecipients) {
            foreach ($recipients as $recipient) {
                $body = $this->engine->render('IsicsOpenMiamMiamBundle:Mail:newsletter.html.twig', array('newsletter' => $newsletter));

                $message = \Swift_Message::newInstance()
                    ->setFrom(array($this->mailerConfig['sender_address'] => $this->mailerConfig['sender_name']))
                    ->setTo($recipient->getEmail())
                    ->setSubject($newsletter->getSubject())
                    ->setBody($body, 'text/html');

                $this->mailer->send($message);
            }
        }

        $newsletter->setSentAt(new \DateTime());
        $this->save($newsletter, $user);

        return $nbRecipients;
    }

    /**
     * Send test newsletter
     *
     * @param Newsletter $newsletter
     * @param User       $user
     */
    public function sendTest(Newsletter $newsletter, User $user)
    {
        $body = $this->engine->render('IsicsOpenMiamMiamBundle:Mail:newsletterTest.html.twig', array('newsletter' => $newsletter));

        $message = \Swift_Message::newInstance()
            ->setFrom(array($this->mailerConfig['sender_address'] => $this->mailerConfig['sender_name']))
            ->setTo($user->getEmail())
            ->setSubject($newsletter->getSubject())
            ->setBody($body, 'text/html');

        $this->mailer->send($message);
    }

    /**
     * Save newsletter and send test
     *
     * @param Newsletter $newsletter
     * @param User       $user
     */
    public function saveAndSendTest(Newsletter $newsletter, User $user)
    {
        $this->save($newsletter);
        $this->sendTest($newsletter, $user);
    }

    /**
     * Returns activities of a newsletter
     *
     * @param Newsletter $newsletter
     *
     * @return array
     */
    public function getActivities(Newsletter $newsletter)
    {
        return $this->entityManager->getRepository('IsicsOpenMiamMiamBundle:Activity')->findByEntities($newsletter, $newsletter->getAssociation());
    }
}