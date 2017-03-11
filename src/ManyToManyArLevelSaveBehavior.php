<?php
/**
 * Created by PhpStorm.
 * User: Vadym Semeniuk
 * Date: 7/18/16
 * Time: 4:13 PM
 */

namespace vadymsemenykv\manyToManyBehavior;

use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

class ManyToManyArLevelSaveBehavior extends Behavior
{
    public $multiRelations = false;
    /**
     * Array with all many to many relations
     * For example:
     *      If we have model Article and model Tag.
     *      Article and Tag relate as ManyToMany, so we have table article_to_tag to store this.
     *      We have relation in Article that look like this:
     *
     *      ```php
     *       * @ property Tag[] $tags
     *      ```
     *
     *      ```php
     *          public $tagIds = [];
     *      ```
     *
     *      ```php
     *      public function getTags()
     *      {
     *          return $this->hasMany(Tag::className(), ['id' => 'tag_id'])
     *              ->viaTable('article_to_tag', ['article_id' => 'id']);
     *      }
     *      ```
     *
     *      We well use behavior config like this:
     *
     *          ```php
     *          'manyToManyRelationSaveBehavior' => [
     *              'class' => ManyToManySaveArLevelBehavior::className(),
     *              'relations' => [
     *                  'tags' => [
     *                      'modelClass' => Tag::className(),
     *                      'attribute' => 'tagIds',
     *                      'pkColumnName' => 'id',
     *                      'deleteAllRelatedEntriesBeforeSave' => true,
     *                  ],
     *              ],
     *          ],
     *          ```
     *
     * @var array
     */
    public $relations = [];

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
        ];
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (empty($this->relations)) {
            throw new InvalidConfigException('The "relations" property must be set.');
        }
    }

    private function getProperty($relationName, $propertyName)
    {
        if (isset($this->relations[$relationName][$propertyName])) {
            return $this->relations[$relationName][$propertyName];
        } else {
            throw new InvalidConfigException('The "' . $propertyName . '" property in relation "' . $relationName . '" must be set.');
        }
    }

    public function afterFind()
    {
        foreach ($this->relations as $relationKey => $relationValue) {
            $this->owner->{$this->getProperty($relationKey, 'attribute')} = ArrayHelper::getColumn(
                $this->owner->{$relationKey},
                $this->getProperty($relationKey, 'pkColumnName')
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function afterSave()
    {
        foreach ($this->relations as $relationKey => $relationValue) {
            $unlinkCondition = isset($relationValue['additionalUnlinkCondition'])
                ? $relationValue['additionalUnlinkCondition']
                : false;
            if ($unlinkCondition) {
                $relation = $this->owner->getRelation($relationKey);
                $viaTable = reset($relation->via->from);
                $viaLinkColumn = array_keys($relation->via->link)[0];
                $unlinkCondition = ArrayHelper::merge($unlinkCondition, [$viaLinkColumn => $this->owner->id]);
                $command = $this->owner->getDb()->createCommand();
                $command->delete($viaTable, $unlinkCondition)->execute();
            } else {
                $this->owner->unlinkAll($relationKey, $this->getProperty($relationKey, 'deleteAllRelatedEntriesBeforeSave'));
            }

            /* @var \yii\db\ActiveRecord $class */
            $class = $this->getProperty($relationKey, 'modelClass');
            $extraColumns = isset($relationValue['extraColumns']) ? $relationValue['extraColumns'] : [];
            $items = $this->owner->{$this->getProperty($relationKey, 'attribute')};

            if (is_array($items)) {
                foreach ($items as $modelId) {
                    $model = $class::findOne($modelId);
                    $this->owner->link($relationKey, $model, $extraColumns);
                }
            }
        }
    }
}