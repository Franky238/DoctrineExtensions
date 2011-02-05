<?php

namespace Gedmo\Translatable\Entity\Repository;

use Doctrine\ORM\EntityRepository,
    Doctrine\ORM\Query,
    Gedmo\Translatable\Exception,
    Gedmo\Translatable\Translatable;

/**
 * The TranslationRepository has some useful functions
 * to interact with translations.
 * 
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @package Gedmo.Translatable.Entity.Repository
 * @subpackage TranslationRepository
 * @link http://www.gediminasm.org
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class TranslationRepository extends EntityRepository
{    
    /**
     * Loads all translations with all translatable
     * fields from the given entity
     * 
     * @param object $entity Must implement Translatable
     * @return array list of translations in locale groups
     */
    public function findTranslations($entity)
    {
        $result = array();
        if ($entity) {
            if ($this->_em->getUnitOfWork()->getEntityState($entity) == \Doctrine\ORM\UnitOfWork::STATE_NEW) {
                return $result;
            }
            $entityClass = get_class($entity);
            $meta = $this->_em->getClassMetadata($entityClass);
            $identifier = $meta->getSingleIdentifierFieldName();
            $entityId = $meta->getReflectionProperty($identifier)->getValue($entity);
            
            $translationMeta = $this->getClassMetadata(); // table inheritance support
            $qb = $this->_em->createQueryBuilder();
            $qb->select('trans.content, trans.field, trans.locale')
                ->from($translationMeta->rootEntityName, 'trans')
                ->where('trans.foreignKey = :entityId', 'trans.objectClass = :entityClass')
                ->orderBy('trans.locale');
            $q = $qb->getQuery();
            $data = $q->execute(
                compact('entityId', 'entityClass'),
                Query::HYDRATE_ARRAY
            );
            
            if ($data && is_array($data) && count($data)) {
                foreach ($data as $row) {
                    $result[$row['locale']][$row['field']] = $row['content'];
                }
            }
        }
        return $result;
    }
    
    /**
     * Find the entity $class by the translated field.
     * Result is the first occurence of translated field.
     * Query can be slow, since there are no indexes on such
     * columns
     * 
     * @param string $field
     * @param string $value
     * @param string $class
     * @return object - instance of $class or null if not found
     */
    public function findObjectByTranslatedField($field, $value, $class)
    {
        $entity = null;
        $meta = $this->_em->getClassMetadata($class);
        $translationMeta = $this->getClassMetadata(); // table inheritance support
        if ($meta->hasField($field)) {
            $dql = "SELECT trans.foreignKey FROM {$translationMeta->rootEntityName} trans";
            $dql .= ' WHERE trans.objectClass = :class';
            $dql .= ' AND trans.field = :field';
            $dql .= ' AND trans.content = :value';
            $q = $this->_em->createQuery($dql);
            $q->setParameters(compact('class', 'field', 'value'));
            $q->setMaxResults(1);
            $result = $q->getArrayResult();
            $id = count($result) ? $result[0]['foreignKey'] : null;
                
            if ($id) {
                $entity = $this->_em->find($class, $id);
            }
        }
        return $entity;
    }
    
    /**
     * Loads all translations with all translatable
     * fields by a given entity primary key
     *
     * @param mixed $id - primary key value of an entity
     * @return array
     */
    public function findTranslationsByObjectId($id)
    {
        $result = array();
        if ($id) {
            $translationMeta = $this->getClassMetadata(); // table inheritance support            
            $qb = $this->_em->createQueryBuilder();
            $qb->select('trans.content, trans.field, trans.locale')
                ->from($translationMeta->rootEntityName, 'trans')
                ->where('trans.foreignKey = :entityId')
                ->orderBy('trans.locale');
            $q = $qb->getQuery();
            $data = $q->execute(
                array('entityId' => $id),
                Query::HYDRATE_ARRAY
            );
            
            if ($data && is_array($data) && count($data)) {
                foreach ($data as $row) {
                    $result[$row['locale']][$row['field']] = $row['content'];
                }
            }
        }
        return $result;
    }
}