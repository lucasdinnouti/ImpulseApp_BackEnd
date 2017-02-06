<?php

/*
 * Copyright 2015 Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Samples\Bookshelf\DataModel;

use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Entity;

/**
 * Class Datastore implements the DataModel with a Google Data Store.
 */
class Datastore implements DataModelInterface
{
    private $datasetId;
    private $datastore;
    protected $columns = [
      'title'       => 'string',
      'date'        => 'timestamp',
      'content'     => 'string',
      'image'       => 'string',
      'author'      => 'string',
      'published'   => 'integer',
      'AbsNum'      => 'integer'
    ];

    public function __construct($projectId)
    {
        $this->datasetId = $projectId;
        $this->datastore = new DatastoreClient([
            'projectId' => $projectId,
        ]);
    }

    public function listPosts($limit = 10, $cursor = null)
    {
        $query = $this->datastore->query()
            ->kind('Post')
            ->order('AbsNum')
            ->limit($limit)
            ->start($cursor);

        $results = $this->datastore->runQuery($query);

        $posts = [];
        $nextPageCursor = null;
        foreach ($results as $entity) {
            $post = $entity->get();
            $post['id'] = $entity->key()->pathEndIdentifier();
            $posts[] = $post;
            $nextPageCursor = $entity->cursor();
        }

        return [
            'posts' => $posts,
            'cursor' => $nextPageCursor,
        ];
    }

    public function create($post, $key = null)
    {
        $this->verifyPost($post);

        $key = $this->datastore->key('Post');
        $entity = $this->datastore->entity($key, $post);

        $this->datastore->insert($entity);

        // return the ID of the created datastore entity
        return $entity->key()->pathEndIdentifier();
    }

    public function read($id)
    {
        $key = $this->datastore->key('Post', $id);
        $entity = $this->datastore->lookup($key);

        if ($entity) {
            $post = $entity->get();
            $post['id'] = $id;
            return $post;
        }

        return false;
    }

    public function update($post)
    {
        $this->verifyPost($post);

        if (!isset($post['id'])) {
            throw new \InvalidArgumentException('Post must have an "id" attribute');
        }

        $transaction = $this->datastore->transaction();
        $key = $this->datastore->key('Post', $post['id']);
        $task = $transaction->lookup($key);
        unset($post['id']);
        $entity = $this->datastore->entity($key, $post);
        $transaction->upsert($entity);
        $transaction->commit();

        // return the number of updated rows
        return 1;
    }

    public function delete($id)
    {
        $key = $this->datastore->key('Post', $id);
        return $this->datastore->delete($key);
    }

    private function verifyPost($post)
    {
        if ($invalid = array_diff_key($post, $this->columns)) {
            throw new \InvalidArgumentException(sprintf(
                'unsupported post properties: "%s"',
                implode(', ', $invalid)
            ));
        }
    }
}
