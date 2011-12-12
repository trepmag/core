<?php
/**
 * Copyright Zikula Foundation 2010 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license MIT
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

use SystemPlugin\Symfony2Forms\Renderer;

/**
 * Symfony2 forms plugin definition.
 */
class SystemPlugin_Symfony2Forms_Plugin extends Zikula_AbstractPlugin implements Zikula_Plugin_AlwaysOnInterface
{
    /**
     * Get plugin meta data.
     *
     * @return array Meta data.
     */
    protected function getMeta()
    {
        return array('displayname' => $this->__('Symfony2 Forms'),
                     'description' => $this->__('Provides Form Component of Symfony2'),
                     'version'     => '1.0.0'
                      );
    }
    
    public function initialize()
    {
        ZLoader::addAutoloader("Symfony\\Component\\Form", __DIR__ . '/lib/vendor', '\\');
        ZLoader::addAutoloader("Symfony\\Component\\EventDispatcher", __DIR__ . '/lib/vendor', '\\');
        ZLoader::addAutoloader("Symfony\\Bridge\\Doctrine", __DIR__ . '/lib/vendor', '\\');
        ZLoader::addAutoloader("SystemPlugin\\Symfony2Forms", __DIR__ . '/lib', '\\');
        
        $registry = new \SystemPlugin\Symfony2Forms\DoctrineRegistryImpl();
        
        $csrf = new \Symfony\Component\Form\Extension\Csrf\CsrfExtension(new \SystemPlugin\Symfony2Forms\ZikulaCsrfProvider());
        $core = new \Symfony\Component\Form\Extension\Core\CoreExtension();
        $zkvalidator = new \SystemPlugin\Symfony2Forms\Validation\Form\ValidatorExtension();
        $zk = new \SystemPlugin\Symfony2Forms\ZikulaExtension();
        $doctrine = new \Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension($registry);
        
        $formFactory = new \Symfony\Component\Form\FormFactory(array($core, $csrf, $zkvalidator, $zk, $doctrine));
        
        $this->serviceManager->attachService('symfony.formfactory', $formFactory);
        
        
        $formRenderer = new \SystemPlugin\Symfony2Forms\FormRenderer($this->eventManager);
        $this->serviceManager->attachService('symfony.formrenderer', $formRenderer);
    }
    
    protected function setupHandlerDefinitions()
    {
        $this->addHandlerDefinition('view.init', 'initView');
        $this->addHandlerDefinition('symfony.formrenderer.lookup', 'registerRenderer');
    }
    
    public function initView(Zikula_Event $event) 
    {
        /* @var $view Zikula_View */
        $view = $event->getSubject();
        
        $view->register_function('sform_enctype', array($this->serviceManager->getService('symfony.formrenderer'), 
                                                    'renderEnctype'));
        
        $view->register_function('sform_row', array($this->serviceManager->getService('symfony.formrenderer'), 
                                                    'renderRow'));
        
        $view->register_function('sform_label', array($this->serviceManager->getService('symfony.formrenderer'), 
                                                    'renderLabel'));
        
        $view->register_function('sform_errors', array($this->serviceManager->getService('symfony.formrenderer'), 
                                                    'renderErrors'));
        
        $view->register_function('sform_widget', array($this->serviceManager->getService('symfony.formrenderer'), 
                                                    'renderWidget'));
        
        $view->register_function('sform_rest', array($this->serviceManager->getService('symfony.formrenderer'), 
                                                    'renderRest'));
        
        $view->register_function('sform_all_errors', array($this->serviceManager->getService('symfony.formrenderer'), 
                                                    'renderGlobalErrors'));
        
        $view->register_block('sform', array($this->serviceManager->getService('symfony.formrenderer'), 
                                                    'renderFormTag'));
    }
    
    public function registerRenderer(Zikula_Event $event)
    {
        $event->getSubject()->append(new Renderer\FieldRow());
        $event->getSubject()->append(new Renderer\FieldLabel());
        $event->getSubject()->append(new Renderer\FieldErrors());
        $event->getSubject()->append(new Renderer\EmailWidget());
        $event->getSubject()->append(new Renderer\FieldWidget());
        $event->getSubject()->append(new Renderer\Attributes());
        $event->getSubject()->append(new Renderer\FieldEnctype());
        $event->getSubject()->append(new Renderer\FormWidget());
        $event->getSubject()->append(new Renderer\ContainerAttributes());
        $event->getSubject()->append(new Renderer\FieldRows());
        $event->getSubject()->append(new Renderer\FieldRest());
        $event->getSubject()->append(new Renderer\HiddenRow());
        $event->getSubject()->append(new Renderer\HiddenWidget());
        $event->getSubject()->append(new Renderer\CheckboxWidget());
        $event->getSubject()->append(new Renderer\ChoiceOptions());
        $event->getSubject()->append(new Renderer\ChoiceWidget());
        $event->getSubject()->append(new Renderer\DateWidget());
        $event->getSubject()->append(new Renderer\DatetimeWidget());
        $event->getSubject()->append(new Renderer\FormLabel());
        $event->getSubject()->append(new Renderer\IntegerWidget());
        $event->getSubject()->append(new Renderer\MoneyWidget());
        $event->getSubject()->append(new Renderer\NumberWidget());
        $event->getSubject()->append(new Renderer\PasswordWidget());
        $event->getSubject()->append(new Renderer\PercentWidget());
        $event->getSubject()->append(new Renderer\PrototypeRow());
        $event->getSubject()->append(new Renderer\RadioWidget());
        $event->getSubject()->append(new Renderer\RepeatedRow());
        $event->getSubject()->append(new Renderer\SearchWidget());
        $event->getSubject()->append(new Renderer\TextareaWidget());
        $event->getSubject()->append(new Renderer\TimeWidget());
        $event->getSubject()->append(new Renderer\UrlWidget());
        $event->getSubject()->append(new Renderer\FormErrors());
    }
}