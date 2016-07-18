Yii2 ManyToMany Active Record Level Behavior
============================================
Extension save many-to-many related data

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist vadymsemeniuk/yii2-many-to-many-ar-level-save-behavior "*"
```

or add

```
"vadymsemeniuk/yii2-many-to-many-ar-level-save-behavior": "*"
```

to the require section of your `composer.json` file.


Usage
-----

An example of usage could be:

Suppose you have the following schema.

```sql
CREATE TABLE `article` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
);

CREATE TABLE `tag` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
);

CREATE TABLE `article_to_tag` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk-article_to_tag-article_id-article-id` (`article_id`),
  KEY `fk-article_to_tag-tag_id-tag-id` (`tag_id`),
  CONSTRAINT `fk-article_to_tag-article_id-article-id` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk-article_to_tag-tag_id-tag-id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)
```

Your code must look like this

```php
use adymsemenykv\manyToManyBehavior\ManyToManyArLevelSaveBehavior

/**
 * @ property Tag[] $tags
 */
class Article extends ActiveRecord {
    public $tagIds = [];
    
    public function tableName() {
        return 'article';
    }
    
    public function getTags()
    {
        return $this->hasMany(Tag::className(), ['id' => 'tag_id'])
            ->viaTable('article_to_tag', ['article_id' => 'id']);
    }
    
    public function behaviors()
    {
        return [
            'manyToManyBehavior' => [
                'class' => ManyToManyArLevelSaveBehavior::className(),
                'relations' => [
                    'tags' => [
                        'modelClass' => Tag::className(),
                        'attribute' => 'tagIds',
                        'pkColumnName' => 'id',
                        'deleteAllRelatedEntriesBeforeSave' => true,
                    ],
                ],
            ],
        ];
    }
}
```

```php
class Tag extends ActiveRecord {

    public function tableName() {
        return 'tag';
    }
}
```

```php
$tag = new Tag();
$tag->id = 1;
$tag->label = 'Tag #1';
$tag->save();


$tag = new Tag();
$tag->id = 2;
$tag->label = 'Tag #2';
$tag->save();

$article = new Article();
$article->label = 'New article';
$article->tagIds = [1, 2];
$article->save();

$tags = $article->tags;

/**
 *  $tags = [
 *     0 => object(Tag)
 *          ...
 *          private '_attributes' (yii\db\BaseActiveRecord) =>
 *                  'id' => int 1
 *                  'label' => string 'Tag #1'
 *          ... 
 *     1 => object(Tag)
 *          ...
 *          private '_attributes' (yii\db\BaseActiveRecord) =>
 *                  'id' => int 2
 *                  'label' => string 'Tag #2'
 *          ...                
 *  ]
 */
```

