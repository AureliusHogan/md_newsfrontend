<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function()
    {

        /**
         * Extend ext:news
         */
        $GLOBALS['TYPO3_CONF_VARS']['EXT']['news']['classes']['Domain/Model/News'][] = 'md_newsfrontend';


        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'Mediadreams.MdNewsfrontend',
            'Newsfe',
            [
                'News' => 'list, new, create, edit, update, delete'
            ],
            // non-cacheable actions
            [
                'News' => 'list, create, update, delete'
            ]
        );

//        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerTypeConverter('Mediadreams\\Mdnewsfrontend\\Property\\TypeConverters\\MyPersistentObjectConverter');


    }
);
