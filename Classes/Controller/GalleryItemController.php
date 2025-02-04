<?php

namespace FelixNagel\GenericGallery\Controller;

/**
 * This file is part of the "generic_gallery" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * GalleryItemController.
 */
class GalleryItemController extends AbstractController
{
    /**
     * {@inheritdoc}
     */
    public function processRequest(RequestInterface $request): ResponseInterface
    {
        /** @var Request $request */

        if (
            !$request->hasArgument('contentElement') ||
            $this->getContentElementUid() !== (int) $request->getArgument('contentElement')
        ) {
            // Request should be handled by another instance of this plugin
            $this->request = $request;
            $this->request->setDispatched(true);

            return new NullResponse();
        }

        return parent::processRequest($request);
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeView(ViewInterface $view)
    {
        $this->template = $this->currentSettings['itemTemplate'];

        parent::initializeView($view);
    }

    /**
     * Display single item.
     *
     * @param string $item
     */
    public function showAction($item): ResponseInterface
    {
        $item = $this->collection->getByIdentifier($item);

        if ($item === null) {
            $this->pageNotFoundAndExit();
        }

        $this->view->assign('item', $item);

        return $this->htmlResponse();
    }
}
