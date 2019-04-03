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
        $news = $this->newsRepository->findByMdNewsfrontendFeuser($this->feuserUid);
        $this->view->assign('news', $news);
    }

    /**
     * action new
     *
     * @return void
     */
    public function newAction()
    {
        
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
        $newNews->setPid($this->getStoragePid($newNews));
        $newNews->setDatetime(new \DateTime()); // make sure, that you have set the correct timezone for $GLOBALS['TYPO3_CONF_VARS']['SYS']['phpTimeZone']
        $newNews->setMdNewsfrontendFeuser($this->feuserObj);

        $this->newsRepository->add($newNews);
        $persistenceManager = $this->objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class);
 
        // persist news entry in order to get the uid of the entry
        $persistenceManager->persistAll();

        $requestArguments = $this->request->getArguments();

        // handle the fileupload
        $this->initializeFileUpload($requestArguments, $newNews);
        
        $this->clearNewsCache($newNews->getUid(), $newNews->getPid());

        $this->addFlashMessage('Die Nachricht wurde erfolgreich angelegt.', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::OK);
        $this->redirect('list');
    }

    /**
     * action edit
     *
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $news
     * @ignorevalidation $news
     * @return void
     */
    public function editAction(\Mediadreams\MdNewsfrontend\Domain\Model\News $news)
    {
        $this->checkAccess($news);
        $this->view->assign('news', $news);
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

        $news->setPid($this->getStoragePid($news));

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

        $this->newsRepository->update($news);
        $this->clearNewsCache($news->getUid(), $news->getPid());

        $this->addFlashMessage('Die Nachricht wurde erfolgreich geändert.', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::OK);
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
        $this->newsRepository->remove($news);

        $this->addFlashMessage('Die Nachricht wurde erfolgreich gelöscht.', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::OK);
        $this->redirect('list');
    }
}
