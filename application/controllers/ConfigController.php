<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

use Icinga\Web\Controller\ActionController;
use Icinga\Web\Notification;
use Icinga\Application\Modules\Module;
use Icinga\Web\Widget;
use Icinga\Application\Icinga;
use Icinga\Application\Config as IcingaConfig;
use Icinga\Form\Config\GeneralConfigForm;
use Icinga\Form\Config\AuthenticationBackendReorderForm;
use Icinga\Form\Config\AuthenticationBackendConfigForm;
use Icinga\Form\Config\ResourceConfigForm;
use Icinga\Form\ConfirmRemovalForm;
use Icinga\Data\ResourceFactory;


/**
 * Application wide controller for application preferences
 */
class ConfigController extends ActionController
{
    public function init()
    {
        $this->view->tabs = Widget::create('tabs')->add('index', array(
            'title' => 'Application',
            'url'   => 'config'
        ))->add('authentication', array(
            'title' => 'Authentication',
            'url'   => 'config/authentication'
        ))->add('resources', array(
            'title' => 'Resources',
            'url'   => 'config/resource'
        ));
    }

    public function devtoolsAction()
    {
        $this->view->tabs = null;
    }

    /**
     * Index action, entry point for configuration
     */
    public function indexAction()
    {
        $form = new GeneralConfigForm();
        $form->setConfig(IcingaConfig::app());
        $form->handleRequest();

        $this->view->form = $form;
        $this->view->tabs->activate('index');
    }

    /**
     * Display the list of all modules
     */
    public function modulesAction()
    {
        $this->view->tabs = Widget::create('tabs')->add('modules', array(
            'title' => 'Modules',
            'url'   => 'config/modules'
        ));

        $this->view->tabs->activate('modules');
        $this->view->modules = Icinga::app()->getModuleManager()->select()
            ->from('modules')
            ->order('enabled', 'desc')
            ->order('name')->paginate();
    }

    public function moduleAction()
    {
        $name = $this->getParam('name');
        $app = Icinga::app();
        $manager = $app->getModuleManager();
        if ($manager->hasInstalled($name)) {
            $this->view->moduleData = Icinga::app()->getModuleManager()->select()
            ->from('modules')->where('name', $name)->fetchRow();
            $module = new Module($app, $name, $manager->getModuleDir($name));
            $this->view->module = $module;
        } else {
            $this->view->module = false;
        }
        $this->view->tabs = $module->getConfigTabs()->activate('info');
    }

    /**
     * Enable a specific module provided by the 'name' param
     */
    public function moduleenableAction()
    {
        $module = $this->getParam('name');
        $manager = Icinga::app()->getModuleManager();
        try {
            $manager->enableModule($module);
            $manager->loadModule($module);
            Notification::success('Module "' . $module . '" enabled');
            $this->rerenderLayout()->reloadCss()->redirectNow('config/modules');
        } catch (Exception $e) {
            $this->view->exceptionMesssage = $e->getMessage();
            $this->view->moduleName = $module;
            $this->view->action = 'enable';
            $this->render('module-configuration-error');
        }
    }

    /**
     * Disable a module specific module provided by the 'name' param
     */
    public function moduledisableAction()
    {
        $module = $this->getParam('name');
        $manager = Icinga::app()->getModuleManager();
        try {
            $manager->disableModule($module);
            Notification::success('Module "' . $module . '" disabled');
            $this->rerenderLayout()->reloadCss()->redirectNow('config/modules');
        } catch (Exception $e) {
            $this->view->exceptionMessage = $e->getMessage();
            $this->view->moduleName = $module;
            $this->view->action = 'disable';
            $this->render('module-configuration-error');
        }
    }

    /**
     * Action for listing and reordering authentication backends
     */
    public function authenticationAction()
    {
        $form = new AuthenticationBackendReorderForm();
        $form->setConfig(IcingaConfig::app('authentication'));
        $form->handleRequest();

        $this->view->form = $form;
        $this->view->tabs->activate('authentication');
        $this->render('authentication/reorder');
    }

    /**
     * Action for creating a new authentication backend
     */
    public function createauthenticationbackendAction()
    {
        $form = new AuthenticationBackendConfigForm();
        $form->setConfig(IcingaConfig::app('authentication'));
        $form->setResourceConfig(ResourceFactory::getResourceConfigs());
        $form->setRedirectUrl('config/authentication');
        $form->handleRequest();

        $this->view->form = $form;
        $this->view->tabs->activate('authentication');
        $this->render('authentication/create');
    }

    /**
     * Action for editing authentication backends
     */
    public function editauthenticationbackendAction()
    {
        $form = new AuthenticationBackendConfigForm();
        $form->setConfig(IcingaConfig::app('authentication'));
        $form->setResourceConfig(ResourceFactory::getResourceConfigs());
        $form->setRedirectUrl('config/authentication');
        $form->handleRequest();

        $this->view->form = $form;
        $this->view->tabs->activate('authentication');
        $this->render('authentication/modify');
    }

    /**
     * Action for removing a backend from the authentication list
     */
    public function removeauthenticationbackendAction()
    {
        $form = new ConfirmRemovalForm(array(
            'onSuccess' => function ($request) {
                $configForm = new AuthenticationBackendConfigForm();
                $configForm->setConfig(IcingaConfig::app('authentication'));
                $authBackend = $request->getQuery('auth_backend');

                try {
                    $configForm->remove($authBackend);
                } catch (InvalidArgumentException $e) {
                    Notification::error($e->getMessage());
                    return;
                }

                if ($configForm->save()) {
                    Notification::success(sprintf(
                        t('Authentication backend "%s" has been successfully removed'),
                        $authBackend
                    ));
                } else {
                    return false;
                }
            }
        ));
        $form->setRedirectUrl('config/authentication');
        $form->handleRequest();

        $this->view->form = $form;
        $this->view->tabs->activate('authentication');
        $this->render('authentication/remove');
    }

    /**
     * Display all available resources and a link to create a new one and to remove existing ones
     */
    public function resourceAction()
    {
        $this->view->resources = IcingaConfig::app('resources', true)->toArray();
        $this->view->tabs->activate('resources');
    }

    /**
     * Display a form to create a new resource
     */
    public function createresourceAction()
    {
        $form = new ResourceConfigForm();
        $form->setConfig(IcingaConfig::app('resources'));
        $form->setRedirectUrl('config/resource');
        $form->handleRequest();

        $this->view->form = $form;
        $this->render('resource/create');
    }

    /**
     * Display a form to edit a existing resource
     */
    public function editresourceAction()
    {
        $form = new ResourceConfigForm();
        $form->setConfig(IcingaConfig::app('resources'));
        $form->setRedirectUrl('config/resource');
        $form->handleRequest();

        $this->view->form = $form;
        $this->render('resource/modify');
    }

    /**
     * Display a confirmation form to remove a resource
     */
    public function removeresourceAction()
    {
        $form = new ConfirmRemovalForm(array(
            'onSuccess' => function ($request) {
                $configForm = new ResourceConfigForm();
                $configForm->setConfig(IcingaConfig::app('resources'));
                $resource = $request->getQuery('resource');

                try {
                    $configForm->remove($resource);
                } catch (InvalidArgumentException $e) {
                    Notification::error($e->getMessage());
                    return;
                }

                if ($configForm->save()) {
                    Notification::success(sprintf(t('Resource "%s" has been successfully removed'), $resource));
                } else {
                    return false;
                }
            }
        ));
        $form->setRedirectUrl('config/resource');
        $form->handleRequest();

        // Check if selected resource is currently used for authentication
        $resource = $this->getRequest()->getQuery('resource');
        $authConfig = IcingaConfig::app('authentication')->toArray();
        foreach ($authConfig as $backendName => $config) {
            if (array_key_exists('resource', $config) && $config['resource'] === $resource) {
                $form->addError(sprintf(
                    $this->translate(
                        'The resource "%s" is currently in use by the authentication backend "%s". ' .
                        'Removing the resource can result in noone being able to log in any longer.'
                    ),
                    $resource,
                    $backendName
                ));
            }
        }

        $this->view->form = $form;
        $this->render('resource/remove');
    }
}
