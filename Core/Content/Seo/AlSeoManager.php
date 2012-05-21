<?php
/*
 * This file is part of the AlphaLemon CMS Application and it is distributed
 * under the GPL LICENSE Version 2.0. To use this application you must leave
 * intact this copyright notice.
 *
 * Copyright (c) AlphaLemon <webmaster@alphalemon.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://www.alphalemon.com
 * 
 * @license    GPL LICENSE Version 2.0
 * 
 */

namespace AlphaLemon\AlphaLemonCmsBundle\Core\Content\Seo;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\TranslatorInterface;
use AlphaLemon\AlphaLemonCmsBundle\Core\Content\Validator\AlParametersValidatorInterface;
use AlphaLemon\AlphaLemonCmsBundle\Core\Model\Orm\SeoModelInterface;
use AlphaLemon\PageTreeBundle\Core\Tools\AlToolkit;
use AlphaLemon\AlphaLemonCmsBundle\Model\AlSeo;
use AlphaLemon\AlphaLemonCmsBundle\Core\Event\Content\SeoEvents;
use AlphaLemon\AlphaLemonCmsBundle\Core\Event\Content;
use AlphaLemon\AlphaLemonCmsBundle\Core\Content\AlContentManagerInterface;
use AlphaLemon\AlphaLemonCmsBundle\Core\Content\Base\AlContentManagerBase;
use AlphaLemon\AlphaLemonCmsBundle\Core\Exception\Event;
use AlphaLemon\AlphaLemonCmsBundle\Core\Exception\Content\General;
use AlphaLemon\AlphaLemonCmsBundle\Core\Exception\Content\Page;
use AlphaLemon\AlphaLemonCmsBundle\Core\Exception\Content\General\InvalidParameterTypeException;

/**
 * AlBlockManager is the object responsible to manage an AlSeo object. 
 *
 * @author alphalemon <webmaster@alphalemon.com>
 */
class AlSeoManager extends AlContentManagerBase implements AlContentManagerInterface
{
    protected $alSeo = null;
    protected $seoModel = null;

    /**
     * Constructor
     * 
     * @param EventDispatcherInterface $dispatcher
     * @param TranslatorInterface $translator
     * @param SeoModelInterface $alSeoModel
     * @param AlParametersValidatorInterface $validator 
     */
    public function __construct(EventDispatcherInterface $dispatcher, TranslatorInterface $translator, SeoModelInterface $alSeoModel, AlParametersValidatorInterface $validator = null)
    {
        parent::__construct($dispatcher, $translator, $validator);
        
        $this->seoModel = $alSeoModel;
    }
    
    /**
     * Sets the seo model object
     * 
     * @param SeoModelInterface $v
     * @return \AlphaLemon\AlphaLemonCmsBundle\Core\Content\Seo\AlSeoManager 
     */
    public function setSeoModel(SeoModelInterface $v)
    {
        $this->seoModel = $v;
        
        return $this;
    }
    
    /**
     * Returns the seo model object associated with this object
     * 
     * @api
     * @return SeoModelInterface 
     */
    public function getSeoModel()
    {
        return $this->seoModel;
    }
    
    /**
     * {@inheritdoc}
     */
    public function get()
    {
        return $this->alSeo;
    }

    /**
     * {@inheritdoc}
     */
    public function set($object = null)
    {
        if (null !== $object && !$object instanceof AlSeo) {
            throw new InvalidParameterTypeException('AlSeoManager is only able to manage AlSeo objects');
        }
        
        $this->alSeo = $object;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        if (null === $this->alSeo || $this->alSeo->getId() == null) {
            
            return $this->add($values);
        }
        else {
            
            return $this->edit($values);
        }
    }
  
    /**
     * {@inheritdoc}
     */
    public function delete()
    {
        if (null !== $this->alSeo)
        {
            try
            {
                if (null !== $this->dispatcher)
                {
                    $event = new  Content\Seo\BeforeSeoDeletingEvent($this);
                    $this->dispatcher->dispatch(SeoEvents::BEFORE_DELETE_SEO, $event);

                    if ($event->isAborted())
                    {
                        throw new \RuntimeException($this->translator->trans("The page attributes deleting action has been aborted", array(), 'al_page_attributes_manager_exceptions'));
                    }
                }
                    
                $this->seoModel->startTransaction(); 
                $result = $this->seoModel
                            ->setModelObject($this->alSeo)
                            ->delete();        
                if ($result && null !== $this->dispatcher)
                {
                    $event = new  Content\Seo\BeforeDeleteSeoCommitEvent($this);
                    $this->dispatcher->dispatch(SeoEvents::BEFORE_DELETE_SEO_COMMIT, $event);

                    if ($event->isAborted()) {
                        $result = false;
                    }
                }
                
                if ($result)
                {
                    $this->seoModel->commit();
                    
                    if (null !== $this->dispatcher)
                    {
                        $event = new  Content\Seo\AfterSeoDeletedEvent($this);
                        $this->dispatcher->dispatch(SeoEvents::AFTER_DELETE_SEO, $event);
                    }
                }
                else
                {
                    $this->seoModel->rollBack();
                }
                
                return $result;
            }
            catch(\Exception $e)
            {
                if (isset($this->seoModel) && $this->seoModel !== null) {
                    $this->seoModel->rollBack();
                }
                
                throw $e;
            }
        }
        else
        {
            throw new General\ParameterIsEmptyException($this->translator->trans('The seo model object is null'));
        }
    }
    
    /**
     * Adds a new AlSeo object from the given params
     * 
     * @param array $values
     * @return Boolean 
     */
    protected function add(array $values)
    {
        try {
            if (null !== $this->dispatcher) {
                $event = new  Content\Seo\BeforeSeoAddingEvent($this, $values);
                $this->dispatcher->dispatch(SeoEvents::BEFORE_ADD_SEO, $event);

                if ($event->isAborted()) {
                    throw new Event\EventAbortedException($this->translator->trans("The seo adding action has been aborted"));
                }

                if ($values !== $event->getValues()) {
                    $values = $event->getValues();
                }
            }
            
            $this->validator->checkEmptyParams($values);
            $this->validator->checkRequiredParamsExists(array('PageId' => '', 'LanguageId' => '', 'Permalink' => ''), $values);
            
            if (empty($values['PageId'])) {
                throw new General\ParameterIsEmptyException($this->translator->trans("The PageId parameter is mandatory to save a seo object"));
            }

            if (empty($values['LanguageId'])) {
                throw new General\ParameterIsEmptyException($this->translator->trans("The LanguageId parameter is mandatory to save a seo object"));
            }
            
            if (empty($values['Permalink'])) {
                throw new General\ParameterIsEmptyException($this->translator->trans("The Permalink parameter is mandatory to save a seo object"));
            }
            
            $values["Permalink"] = AlToolkit::slugify($values["Permalink"]);
        
            $this->seoModel->startTransaction();
            if (null === $this->alSeo) {
                $this->alSeo = new AlSeo();
            }
            
            $result = $this->seoModel
                    ->setModelObject($this->alSeo)
                    ->save($values);    
            if ($result)
            {
                if (null !== $this->dispatcher)
                {
                    $event = new  Content\Seo\BeforeAddSeoCommitEvent($this, $values);
                    $this->dispatcher->dispatch(SeoEvents::BEFORE_ADD_SEO_COMMIT, $event);

                    if ($event->isAborted()) {
                        $result = false;
                    }
                }
            }
            
            if ($result)
            {
                $this->seoModel->commit();
              
                if (null !== $this->dispatcher)
                {
                    $event = new  Content\Seo\AfterSeoAddedEvent($this);
                    $this->dispatcher->dispatch(SeoEvents::AFTER_ADD_SEO, $event);
                }
            }
            else
            {
                $this->seoModel->rollBack();
            }
            
            return $result;
        }
        catch(\Exception $e)
        {
            if (isset($this->seoModel) && $this->seoModel !== null) {
                $this->seoModel->rollBack();
            }
            
            throw $e;
        }
    }
    
    /**
     * Edits the managed page attributes object
     * 
     * @param array $values
     * @return Boolean 
     */
    protected function edit(array $values = array())
    {
        try
        {
            if (null !== $this->dispatcher) {
                $event = new  Content\Seo\BeforeSeoEditingEvent($this, $values);
                $this->dispatcher->dispatch(SeoEvents::BEFORE_EDIT_SEO, $event);

                if ($event->isAborted()) {
                    throw new \RuntimeException($this->translator->trans("The page attributes editing action has been aborted", array(), 'al_page_attributes_manager_exceptions'));
                }

                if ($values !== $event->getValues()) {
                    $values = $event->getValues();
                }
            }
            
            $this->validator->checkEmptyParams($values);
            $this->validator->checkOnceValidParamExists(array('Permalink' => '', 'MetaTitle' => '', 'MetaDescription' => '', 'MetaKeywords' => ''), $values);
            
            if (isset($values['Permalink'])) {
                $currentPermalink = $this->alSeo->getPermalink();
                if ($values['Permalink'] != $currentPermalink) {
                    $values["oldPermalink"] = $currentPermalink;
                    $values['Permalink'] = AlToolkit::slugify($values["Permalink"]);
                }
                else {
                    unset($values['Permalink']);
                }
            }
            
            if (isset($values['MetaTitle']) && $values['MetaTitle'] == $this->alSeo->getMetaTitle()) {
                unset($values['MetaTitle']);
            }
            
            if (isset($values['MetaDescription']) && $values['MetaDescription'] == $this->alSeo->getMetaDescription()) {
                unset($values['MetaDescription']);
            }
            
            if (isset($values['MetaKeywords']) && $values['MetaKeywords'] == $this->alSeo->getMetaKeywords()) {
                unset($values['MetaKeywords']);
            }
            
            $this->seoModel->startTransaction();
            $this->seoModel->setModelObject($this->alSeo);
            $res = (!empty($values)) ? $this->seoModel->save($values) : true;
            
            if ($res && null !== $this->dispatcher) {
                $event = new Content\Seo\BeforeEditSeoCommitEvent($this, $values);
                $this->dispatcher->dispatch(SeoEvents::BEFORE_EDIT_SEO_COMMIT, $event);
            }

            if ($res) {
                $this->seoModel->commit();

                if (null !== $this->dispatcher) {
                    $event = new Content\Seo\AfterSeoEditedEvent($this);
                    $this->dispatcher->dispatch(SeoEvents::AFTER_EDIT_SEO, $event);
                }

                return true;
            }
            else {
                $this->seoModel->rollBack();
                return false;
            }
        }
        catch(\Exception $e) {
            if (isset($this->seoModel) && $this->seoModel !== null) {
                $this->seoModel->rollBack();
            }
            
            throw $e;
        }
    }
}
