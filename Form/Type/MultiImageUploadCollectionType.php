<?php
/*
 * This file is part of NeutronFormBundle
 *
 * (c) Nikolay Georgiev <azazen09@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Neutron\FormBundle\Form\Type;

//use Neutron\FormBundle\Exception\ImagesNotFoundException;

use Neutron\FormBundle\Model\MultiImageInterface;

use Doctrine\Common\Collections\Collection;

use Neutron\FormBundle\Manager\ImageManagerInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Symfony\Component\HttpFoundation\Session\Session;

use Symfony\Component\Form\FormView;

use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use Symfony\Component\OptionsResolver\Options;

use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Bundle\FrameworkBundle\Routing\Router;

use Symfony\Component\Form\FormInterface;

use Symfony\Component\Form\AbstractType;

/**
 * This class creates multi image upload collection element
 *
 * @author Nikolay Georgiev <azazen09@gmail.com>
 * @since 1.0
 */
class MultiImageUploadCollectionType extends AbstractType
{

    /**
     * @var \Symfony\Component\HttpFoundation\Session\Session
     */
    protected $session;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\Routing\Router
     */
    protected $router;
    
    /**
     * @var \Symfony\Component\EventDispatcher\EventSubscriberInterface
     */
    protected $subscriber;
    
    /**
     * @var \Neutron\FormBundle\Manager\ImageManagerInterface
     */
    protected $imageManager;

    /**
     * @var array
     */
    protected $options;

    /**
     * Construct
     *
     * @param \Symfony\Component\HttpFoundation\Session $session
     * @param \Symfony\Bundle\FrameworkBundle\Routing\Router $router
     * @param \Symfony\Bundle\FrameworkBundle\Routing\Router $router
     * @param array $options
     */
    public function __construct(Session $session, Router $router, EventSubscriberInterface $subscriber, 
            ImageManagerInterface $imageManager, array $options)
    {
        $this->session = $session;
        $this->router = $router;
        $this->subscriber = $subscriber;
        $this->imageManager = $imageManager;
        $this->options = $options;
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Form.AbstractType::buildForm()
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {   
        $builder->addEventSubscriber($this->subscriber);
    }
    
    /**
     * (non-PHPdoc)
     * @see \Symfony\Component\Form\AbstractType::finishView()
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {   
        $options['configs']['id'] = $view->vars['id'];
        $this->session->set($view->vars['id'], $options['configs']);
        $view->vars['configs'] = $options['configs'];

        $collection = $form->getData();
        
        if ($collection instanceof Collection){
            
            foreach ($collection as $image){
                
                if ($image instanceof MultiImageInterface && null !== $image->getId()){
                    $override = ($image->getHash() != $this->imageManager->getImageInfo($image)->getTemporaryImageHash());
                    try {
                        $this->imageManager->copyImagesToTemporaryDirectory($image);
                    } catch (ImagesNotFoundException $e){
                        // do nothing
                    }   
                }
            }
        }
    }
    
    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Form.AbstractType::setDefaultOptions()
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $defaultOptions = $this->options;
    
        $router = $this->router;
    
        $resolver->setDefaults(array(
            'allow_add' => true,
            'allow_delete' => true,
            'error_bubbling' => false,
            'translation_domain' => 'NeutronFormBundle',
            'configs' => array(),
        ));
    
        $resolver->setNormalizers(array(
            'type' => function (Options $options, $value) use ($defaultOptions, $router){
                  return 'neutron_multi_image_upload';
            },
            'configs' => function (Options $options, $value) use ($defaultOptions, $router){
                $configs = array_replace_recursive($defaultOptions, $value);

                if (!isset($configs['minWidth']) || !isset($configs['minWidth'])){
                    throw new \InvalidArgumentException('configs:minWidth or configs:minHeight is missing.');
                }

                $configs['upload_url'] = $router->generate('neutron_form_media_image_upload');
                $configs['crop_url'] = $router->generate('neutron_form_media_image_crop');
                $configs['rotate_url'] = $router->generate('neutron_form_media_image_rotate');
                $configs['reset_url'] = $router->generate('neutron_form_media_image_reset');
                $configs['dir'] = '/' . $defaultOptions['temporary_dir'] . '/';
                $configs['enabled_value'] = false;

                return $configs;
            }
        ));
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Form.AbstractType::getParent()
     */
    public function getParent()
    {
        return 'collection';
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Form.FormTypeInterface::getName()
     */
    public function getName()
    {
        return 'neutron_multi_image_upload_collection';
    }

}