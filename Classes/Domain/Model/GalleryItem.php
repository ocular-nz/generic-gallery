<?php

namespace FelixNagel\GenericGallery\Domain\Model;

/**
 * This file is part of the "generic_gallery" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Resource\FileReference as CoreFileReference;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference as ExtbaseFileReference;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Service\ImageService;

/**
 * Class GalleryItem.
 */
class GalleryItem extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    const FILE_REFERENCE_IDENTIFIER_PREFIX = 'file-';

    /**
     * tt_content UID.
     *
     * @var int
     */
    protected $ttContentUid;

    /**
     * title.
        *
     * @var string
     */
    protected $title;

    /**
     * link.
     *
     * @var string
     */
    protected $link;

    /**
     * imageReference.
     *
     * @var \TYPO3\CMS\Extbase\Domain\Model\FileReference
     */
    protected $imageReference = null;

    /**
     * image.
     *
     * @var \TYPO3\CMS\Core\Resource\File
     */
    protected $image = null;

    /**
     * textItems.
     *
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\FelixNagel\GenericGallery\Domain\Model\TextItem>
     */
    protected $textItems;

    /**
     * Construct class.
     */
    public function __construct()
    {
        $this->textItems = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
    }

    /**
     * @return int|string
     */
    public function getIdentifier()
    {
        $identifier = $this->getUid();

        if ($identifier === null) {
            $identifier = self::FILE_REFERENCE_IDENTIFIER_PREFIX.$this->getImage()->getUid();
        }

        return $identifier;
    }

    /**
     * If object is virtual.
     *
     * Virtual means it's generated and not a DB relation
     * So, if the object is virtual the plugin is of type
     * 'images' or 'collection'
     *
     * @return bool
     */
    public function isVirtual()
    {
        return !((bool) parent::getUid());
    }

    /**
     * @param int $ttContentUid
     */
    public function setTtContentUid($ttContentUid)
    {
        $this->ttContentUid = $ttContentUid;
    }

    /**
     * @return int
     */
    public function getTtContentUid()
    {
        return $this->ttContentUid;
    }

    /**
     * Returns the title.
     *
     * @return string $title
     */
    public function getTitle()
    {
        if ($this->isVirtual()) {
            return $this->getImageData()['title'];
        }

        return $this->title;
    }

    /**
     * Sets the title.
     *
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Returns the link.
     *
     * @return string $link
     */
    public function getLink()
    {
        if ($this->isVirtual() || $this->link === '') {
            if ($this->getImageReference() !== null &&
                $this->getImageReference()->getOriginalResource()->getProperty('crop') !== null
            ) {
                // Render cropped image if reference with crop available
                return $this->getCroppedImageLinkFromReference();
            }

            return $this->getImage()->getPublicUrl();
        }

        return $this->link;
    }

    /**
     * Get url to cropped image from reference.
     *
     * @return string
     */
    protected function getCroppedImageLinkFromReference()
    {
        /* @var $objectManager \TYPO3\CMS\Extbase\Object\ObjectManagerInterface */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        /* @var $imageService ImageService */
        $imageService = $objectManager->get(ImageService::class);

        $processedImage = $imageService->applyProcessingInstructions(
            $this->getImageReference()->getOriginalResource(),
            ['crop' => $this->getImageReference()->getOriginalResource()->getProperty('crop')]
        );

        return $imageService->getImageUri($processedImage);
    }

    /**
     * Sets the link.
     *
     * @param string $link
     */
    public function setLink($link)
    {
        $this->link = $link;
    }

    /**
     * Returns the image.
     *
     * @return \TYPO3\CMS\Core\Resource\File $image
     */
    public function getImage()
    {
        if ($this->image === null) {
            return $this->getImageReference()->getOriginalResource()->getOriginalFile();
        }

        return $this->image;
    }

    /**
     * Shortcut for image properties.
     *
     * @return array
     */
    public function getImageData()
    {
        $imageData = $this->getImage()->getProperties();

        if ($this->getImageReference() !== null) {
            // Overwrite with merged reference inline data
            $imageData = $this->getImageReference()->getOriginalResource()->getProperties();
        }

        // Merge with modified meta data
        $imageData = array_merge($imageData, $this->getAdditionalImageProperties());

        return $imageData;
    }

    /**
     * Return formatted image properties.
     *
     * @return array
     */
    protected function getAdditionalImageProperties()
    {
        $properties = $this->getImage()->getProperties();

        if (ExtensionManagementUtility::isLoaded('metadata')) {
            return $this->processPropertiesForMetadaExtension($properties);
        }

        if (ExtensionManagementUtility::isLoaded('extractor')) {
            return $this->processPropertiesForExtractorExtension($properties);
        }

        return [];
    }

    /**
     * @param array $properties
     * @return array
     */
    protected function processPropertiesForExtractorExtension(array $properties)
    {
        $data = [];

        // Process exif data
        $data['shutter_speed'] = $properties['shutter_speed'].'s';
        $data['aperture'] = 'f/'.$properties['aperture'];
        $data['focal_length'] = $properties['focal_length'].'mm';
        $data['iso_speed'] = 'ISO'.$properties['iso_speed'];

        return $data;
    }

    /**
     * @param array $properties
     * @return array
     */
    protected function processPropertiesForMetadaExtension(array $properties)
    {
        $data = [];

        // Process exif data
        $data['shutter_speed_value'] = $properties['shutter_speed_value'].'s';
        $data['aperture_value'] = 'f/'.$properties['aperture_value'];
        $data['focal_length'] = $properties['focal_length'].'mm';
        $data['iso_speed_ratings'] = 'ISO'.$properties['iso_speed_ratings'];

        // Process flash data
        if (isset($GLOBALS['TCA']['sys_file_metadata']['columns']['flash']['config']['items'])) {
            $items = (array) $GLOBALS['TCA']['sys_file_metadata']['columns']['flash']['config']['items'];
            foreach ($items as $item) {
                if ((int) $item[1] === (int) $properties['flash']) {
                    $data['flash'] = $item[0];
                }
            }
        }

        return $data;
    }

    /**
     * Sets the image.
     *
     * @param \TYPO3\CMS\Core\Resource\File $image
     */
    public function setImage($image)
    {
        $this->image = $image;
    }

    /**
     * Sets the imageReference.
     *
     * @param CoreFileReference|ExtbaseFileReference $imageReference
     */
    public function setImageReference($imageReference)
    {
        $fileReference = $imageReference;

        // Normalize to extbase file reference
        if ($imageReference instanceof CoreFileReference) {
            $fileReference = new ExtbaseFileReference();
            $fileReference->setOriginalResource($imageReference);
        }

        $this->imageReference = $fileReference;
    }

    /**
     * Gets the imageReference.
     *
     * @return ExtbaseFileReference
     */
    public function getImageReference()
    {
        return $this->imageReference;
    }

    /**
     * Sets the textItems.
     *
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage $textItems
     *
     * @api
     */
    public function setTextItems(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $textItems)
    {
        $this->textItems = $textItems;
    }

    /**
     * Adds a textItem.
     *
     * @param \FelixNagel\GenericGallery\Domain\Model\TextItem $textItems
     *
     * @api
     */
    public function addTextItem(\FelixNagel\GenericGallery\Domain\Model\TextItem $textItems)
    {
        $this->textItems->attach($textItems);
    }

    /**
     * Removes a textItem.
     *
     * @param \FelixNagel\GenericGallery\Domain\Model\TextItem $textItems
     *
     * @api
     */
    public function removeTextItem(\FelixNagel\GenericGallery\Domain\Model\TextItem $textItems)
    {
        $this->textItems->detach($textItems);
    }

    /**
     * Returns the textItems.
     *
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage An object storage containing the textItems
     *
     * @api
     */
    public function getTextItems()
    {
        return $this->textItems;
    }
}
