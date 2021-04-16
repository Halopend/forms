<?php

declare(strict_types=1);

namespace Bolt\BoltForms\EventSubscriber;

use Bolt\BoltForms\Event\PostSubmitEvent;
use Bolt\Log\LoggerTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Tightenco\Collect\Support\Collection;

class Redirect implements EventSubscriberInterface
{
    use LoggerTrait;

    /** @var PostSubmitEvent */
    private $event;

    /** @var Collection */
    private $feedback;

    /** @var UrlMatcherInterface */
    private $urlMatcher;

    /**
     * Redirect constructor.
     */
    public function __construct(UrlMatcherInterface $urlMatcher)
    {
        $this->urlMatcher = $urlMatcher;
    }

    public function handleEvent(PostSubmitEvent $event): void
    {
        $this->event = $event;

        // Don't redirect, if the form isn't valid
        if (! $this->event->getForm()->isValid()) {
            return;
        }

        $this->formConfig = $this->event->getFormConfig();
        $this->feedback = new Collection($this->formConfig->get('feedback'));
        if ($this->feedback->get('redirect')) {
            $this->redirect();
        }
    }

    public function redirect(): void
    {
        if (isset($this->formConfig->get('submission')['ajax']) && $this->formConfig->get('submission')['ajax']) {
            return;
        }

        if (isset($this->feedback->get('redirect')['target']) && ! empty($this->feedback->get('redirect')['target'])) {
            $response = $this->getRedirectResponse($this->feedback->get('redirect')['target']);

            if ($response instanceof RedirectResponse) {
                $response->send();
            }
        }

        throw new HttpException(Response::HTTP_FOUND, '', null, []);
    }

    protected function getRedirectResponse($target)
    {
        if ((mb_strpos($target, 'http') === 0) || (mb_strpos($target, '#') === 0)) {
            return new RedirectResponse($target);
        }
        try {
            $url = '/' . ltrim($target, '/');
            $this->urlMatcher->match($url);

            return new RedirectResponse($url);
        } catch (ResourceNotFoundException $e) {
            // No route found… Go home site admin, you're… um… putting a bad route in!
            return $this->valid = false;
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            'boltforms.post_submit' => ['handleEvent', 5],
        ];
    }
}
