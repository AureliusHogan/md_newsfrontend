<?php
namespace Mediadreams\MdNewsfrontend\Controller;

/**
 *
 * This file is part of the "News frontend" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2019 Christoph Daecke <typo3@mediadreams.org>
 *
 */

use Mediadreams\MdNewsfrontend\Property\TypeConverters\MyPersistentObjectConverter;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

use Mediadreams\MdNewsfrontend\Service\NewsSlugHelper;

/**
 * NewsController
 */
class NewsController extends BaseController
{
    /**
     * action list
     *
     * @return void
     */
    public function listAction()
    {
        $news = $this->newsRepository->findByFeuserId($this->feuserUid, $this->settings['allowNotEnabledNews']);
//        $news = $this->newsRepository->findByTxMdNewsfrontendFeuser($this->feuserUid);
        $this->view->assign('news', $news);
    }

    /**
     * action new
     *
     * @return void
     */
    public function newAction()
    {
        $this->view->assignMultiple(
            [
                'user' => $this->feuserObj,
                'showinpreviewOptions' => $this->getValuesForShowinpreview()
            ]
        );
    }

    /**
     * Initialize create action
     * Add custom validator for file upload
     *
     * @return void
     */
    public function initializeCreateAction()
    {
        $this->initializeCreateUpdate(
            $this->request->getArguments(),
            $this->arguments['newNews']
        );
    }

    /**
     * action create
     *
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $newNews
     * @return void
     */
    public function createAction(\Mediadreams\MdNewsfrontend\Domain\Model\News $newNews)
    {
        // if no value is provided for field datetime, use current date
        if (!$newNews->getDatetime() instanceof \DateTime) {
            $newNews->setDatetime(new \DateTime()); // make sure, that you have set the correct timezone for $GLOBALS['TYPO3_CONF_VARS']['SYS']['phpTimeZone']
        }

        $newNews->setTxMdNewsfrontendFeuser($this->feuserObj);

        // add signal slot BeforeSave
        $this->signalSlotDispatcher->dispatch(
            __CLASS__,
            __FUNCTION__ . 'BeforeSave',
            [$newNews, $this]
        );

        $this->newsRepository->add($newNews);
        $persistenceManager = $this->objectManager->get(PersistenceManager::class);

        // persist news entry in order to get the uid of the entry
        $persistenceManager->persistAll();

        // generate and set slug for news record
        $slugHelper = GeneralUtility::makeInstance(NewsSlugHelper::class);
        $slug = $slugHelper->getSlug($newNews);
        $newNews->setPathSegment($slug);
        $this->newsRepository->update($newNews);

        $requestArguments = $this->request->getArguments();

        // handle the fileupload
        $this->initializeFileUpload($requestArguments, $newNews);

        // add signal slot AfterPersist
        $this->signalSlotDispatcher->dispatch(
            __CLASS__,
            __FUNCTION__ . 'AfterPersist',
            [$newNews, $this]
        );

        $this->clearNewsCache($newNews->getUid(), $newNews->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.new_success','md_newsfrontend'),
            '',
            AbstractMessage::OK
        );

        $this->redirect('list');
    }

    /**
     * Initialize edit action
     * Add custom validator for file upload
     *
     * @return void
     */
    public function initializeEditAction()
    {
//        $this->arguments->getArgument('news')
//            ->getPropertyMappingConfiguration()
//            ->setTypeConverterOptions('\\Mediadreams\\Mdnewsfrontend\\Property\\TypeConverters\\MyPersistentObjectConverterXX',[
//                'IGNORE_ENABLE_FIELDS',
//                'RESPECT_STORAGE_PAGE',
//                'RESPECT_SYS_LANGUAGE'
//            ]);

        $this->arguments->getArgument('news')
            ->getPropertyMappingConfiguration()
            ->setTypeConverter($this->objectManager->get(MyPersistentObjectConverter::class));

//        $myConverter = new MyPersistentObjectConverter();
//        $myConverter = $this->objectManager->get(MyPersistentObjectConverter::class);
//        $myConverter = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(MyPersistentObjectConverter::class);
//        $this->arguments->getArgument('news')
//            ->getPropertyMappingConfiguration()
//            ->setTypeConverter($myConverter);

//        $this->arguments->getArgument('news')
//            ->getPropertyMappingConfiguration()
//            ->setTypeConverter( 'MyPersistentObjectConverter::class');
//
//        http://dev.b2i.develop.avibus/my-messages?tx_mdnewsfrontend_newsfe%5Baction%5D=eobjectManagerdit&tx_mdnewsfrontend_newsfe%5Bcontroller%5D=News&tx_mdnewsfrontend_newsfe%5Bnews%5D=21&cHash=7aae048c6262e002f668fa617c21e20a
//        $this->arguments->getArgument('news')->getPropertyMappingConfiguration()->setTypeConverter(\Mediadreams\MdNewsfrontend\Property\TypeConverters\MyPersistentObjectConverter::class);
    }

    /**
     * action edit
     *
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $news
     * @TYPO3\CMS\Extbase\Annotation\IgnoreValidation("news")
     * @return void
     */
    public function editAction(\Mediadreams\MdNewsfrontend\Domain\Model\News $news)
    {
        $this->checkAccess($news);

        $this->view->assignMultiple(
            [
                'news' => $news,
                'showinpreviewOptions' => $this->getValuesForShowinpreview()
            ]
        );
    }

    /**
     * Initialize update action
     * Add custom validator for file upload
     *
     * @return void
     */
    public function initializeUpdateAction()
    {
        $this->initializeCreateUpdate(
            $this->request->getArguments(),
            $this->arguments['news']
        );
    }

    /**
     * action update
     *
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $news
     * @return void
     */
    public function updateAction(\Mediadreams\MdNewsfrontend\Domain\Model\News $news)
    {
        $this->checkAccess($news);

        $requestArguments = $this->request->getArguments();

        // Remove file relation from news record
        foreach ($this->uploadFields as $fieldName) {
            if ($requestArguments[$fieldName]['delete'] == 1) {
                $removeMethod = 'remove'.ucfirst($fieldName);
                $getFirstMethod = 'getFirst'.ucfirst($fieldName);

                $news->$removeMethod($news->$getFirstMethod());
            }
        }


        // handle the fileupload
        $this->initializeFileUpload($requestArguments, $news);

        // add signal slot BeforeSave
        $this->signalSlotDispatcher->dispatch(
            __CLASS__,
            __FUNCTION__ . 'BeforeSave',
            [$news, $this]
        );

        $this->newsRepository->update($news);
        $this->clearNewsCache($news->getUid(), $news->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.edit_success','md_newsfrontend'),
            '',
            AbstractMessage::OK
        );

        $this->redirect('list');
    }

    /**
     * action delete
     *
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $news
     * @return void
     */
    public function deleteAction(\Mediadreams\MdNewsfrontend\Domain\Model\News $news)
    {
        $this->checkAccess($news);

        // add signal slot BeforeSave
        $this->signalSlotDispatcher->dispatch(
            __CLASS__,
            __FUNCTION__ . 'BeforeDelete',
            [$news, $this]
        );

        $this->newsRepository->remove($news);

        $this->clearNewsCache($news->getUid(), $news->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.delete_success','md_newsfrontend'),
            '',
            AbstractMessage::OK
        );

        $this->redirect('list');
    }
}
