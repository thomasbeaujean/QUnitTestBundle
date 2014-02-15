<?php

namespace thomasbeaujean\QUnitTestBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\AutoExpireFlashBag;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * QUnitToolbarListener injects the qunit toolbar
 *
 * The onKernelResponse method must be connected to the kernel.response event.
 *
 * The WDT is only injected on well-formed HTML (with a proper </body> tag).
 * This means that the WDT is never included in sub-requests or ESI requests.
 *
 */
class QUnitToolbarListener implements EventSubscriberInterface
{
    const DISABLED = 1;
    const ENABLED  = 2;

    protected $twig;
    protected $interceptRedirects;
    protected $mode;
    protected $filesTest;

    public function __construct(\Twig_Environment $twig, $enable, $filesTest)
    {
        $this->twig = $twig;
        if ($enable === true) {
            $this->mode = self::ENABLED;
        } else {
            $this->mode = self::DISABLED;
        }
        $this->filesTest = $filesTest;
    }

    public function isEnabled()
    {
        return ($this->mode === self::ENABLED);
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        // do not capture redirects or modify XML HTTP Requests
        if ($request->isXmlHttpRequest()) {
            return;
        }

        if ($this->isEnabled()) {
        	$this->injectToolbar($response);
        }
    }

    /**
     * Injects the web debug toolbar into the given Response.
     *
     * @param Response $response A Response instance
     */
    protected function injectToolbar(Response $response)
    {
        if (function_exists('mb_stripos')) {
            $posrFunction   = 'mb_strripos';
            $substrFunction = 'mb_substr';
        } else {
            $posrFunction   = 'strripos';
            $substrFunction = 'substr';
        }

        $content = $response->getContent();
        $pos = $posrFunction($content, '</body>');

        if (false !== $pos) {
            $toolbar = "\n".str_replace("\n", '', $this->twig->render(
                '@QUnitTest/library/results.html.twig',
                array(
                )
            ))."\n";

            $content = $substrFunction($content, 0, $pos).$toolbar.$substrFunction($content, $pos);
            $response->setContent($content);
        }
        if (false !== $pos) {
            $toolbar = "\n".str_replace("\n", '', $this->twig->render(
                    '@QUnitTest/library/include.html.twig',
                    array(
                        'filesTest' => $this->filesTest
                    )
            ))."\n";

            $content = $substrFunction($content, 0, $pos).$toolbar.$substrFunction($content, $pos);
            $response->setContent($content);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => array('onKernelResponse', -128),
        );
    }
}
