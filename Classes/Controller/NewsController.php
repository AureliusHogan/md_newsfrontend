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
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Database\ConnectionPool;
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
        $this->logger->info('ekm: listed message');

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
        $this->logger->info('ekm: created message ' . $newNews->getTitle());
        $this->informUsers($newNews, 'C');

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
        $this->arguments->getArgument('news')
            ->getPropertyMappingConfiguration()
            ->setTypeConverter($this->objectManager->get(MyPersistentObjectConverter::class));
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
        $this->arguments->getArgument('news')
            ->getPropertyMappingConfiguration()
            ->setTypeConverter($this->objectManager->get(MyPersistentObjectConverter::class));

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

        $this->informUsers($news, 'U');

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

    /**
     * get a list of emails ideas receivers
     *
     */
    public function getReceiverEmailAddress(){

        $tableName = 'fe_users';
        $groupId = $this->backendConfiguration->get('sitepackage', 'IdeasGID');

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);

        /**
         * select the fields that will be returned, use asterisk for all
         */
        $queryBuilder->select('first_name', 'last_name', 'email');
        $queryBuilder->from($tableName);
        $queryBuilder->where(
            $queryBuilder->expr()->inSet('usergroup', $queryBuilder->createNamedParameter($groupId, \PDO::PARAM_INT))
        );

        $this->logger->info('SQL = ' . $queryBuilder->getSQL(), $queryBuilder->getParameters());
        return $queryBuilder->execute()->fetchAll();
    }

    /**
     * inform Markets
     *
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $news The news object
     * @param string $action The action happened (creted/updated)
     */
    public function informUsers( \Mediadreams\MdNewsfrontend\Domain\Model\News $news, string $action) {

        if( $news->getHidden()){
            return;
        }
        $creater = new Address(
            $this->feuserObj->getEmail(),
            $this->feuserObj->getFirstName() . ' ' . $this->feuserObj->getLastName()
        );

        $globalReceivers = new Address(
            'ideas@b2hv.com',
            'b2 Ideas Pool'
        );

        $recipientList = $this->getReceiverEmailAddress();
        $recipients = [];
        foreach ($recipientList as $recipient ){
            $recipients[] = new Address(
                $recipient['email'],
                $recipient['first_name'] . ' ' . $recipient['last_name']
            );
        }

        // create the email to creater
        $this->sendTemplateEmail(
            [$creater],
            [$globalReceivers],
            'Intranet Ideas ' . $news->getTitle(),
            'EmailIdeasCreater',
            array(
                'data' => $news,
                'actionDone' => $action,
                'domain' => $this->getDomain(),
                'user' => $this->feuserObj,
                'recipients' => $recipients
            )
        );
        $this->logger->info('Ideas Reciept: ' . $this->feuserObj->getEmail());

        $this->sendTemplateEmail(
            $recipients,
            [$creater],
            'Intranet Ideas' . $news->getTitle(),
            'EmailIdeas',
            array(
                'data' => $news,
                'actionDone' => $action,
                'domain' => $this->getDomain(),
                'user' => $this->feuserObj,
                'recipients' => $recipients
            )
        );
        $this->logger->info('Ideas Informed: ' . json_encode($recipientList));
    }



}
