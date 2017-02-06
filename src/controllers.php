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

namespace Google\Cloud\Samples\Bookshelf;

/*
 * Adds all the controllers to $app.  Follows Silex Skeleton pattern.
 */
use Google\Cloud\Datastore\Blob;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Google\Cloud\Samples\Bookshelf\DataModel\DataModelInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

$app->get('/', function (Request $request) use ($app) {
    return $app->redirect('/posts/');
});

// [START index]
$app->get('/posts/', function (Request $request) use ($app) {
    /** @var DataModelInterface $model */
    $model = $app['bookshelf.model'];
    /** @var Twig_Environment $twig */
    $twig = $app['twig'];
    $token = $request->query->get('page_token');
    $postList = $model->listPosts($app['bookshelf.page_size'], $token);

    return $twig->render('list.html.twig', array(
        'posts' => $postList['posts'],
        'next_page_token' => $postList['cursor'],
    ));
});

$app->post('/lists/', function (Request $request) use ($app) {
    /** @var DataModelInterface $model */
    $model = $app['bookshelf.model'];
    /** @var Twig_Environment $twig */
    //$twig = $app['twig'];
    $token = $request->query->get('page_token');
    $postList = $model->listPosts($app['bookshelf.page_size'], $token);

    // $resposta = "";

    // foreach ($postList as $pubs) {
    //     foreach ($pubs as $sp) {
    //         $resposta = $resposta."{";
    //         $resposta = $resposta.'"title":"'.$sp['title'].'", ';
    //         $resposta = $resposta.'"author":"'.$sp['author'].'", ';
    //         $resposta = $resposta.'"date":"'.$sp['date'].'", ';
    //         $resposta = $resposta.'"content":"'.$sp['content'].'", ';
    //         $resposta = $resposta."}";
    //     }

    // }

    $response = new JsonResponse();
    $response->setData($postList);
    $response->setStatusCode(Response::HTTP_OK);

    return $response;

    //return new Response($resposta, Response::HTTP_OK);
});
// [END index]


// [START add]
$app->get('/posts/add', function () use ($app) {
    /** @var Twig_Environment $twig */
    $twig = $app['twig'];

    return $twig->render('form.html.twig', array(
        'action' => 'Add',
        'post' => array(),
    ));
});

$app->get('/testeFile/', function () use ($app) {
    /** @var Twig_Environment $twig */
    $twig = $app['twig'];

    return $twig->render('testeFile.html.twig');
});

$app->post('/posts/add', function (Request $request) use ($app) {
    /** @var DataModelInterface $model */
    $model = $app['bookshelf.model'];

    $token = $request->query->get('page_token');
    $postList = $model->listPosts($app['bookshelf.page_size'], $token);

    $post = $request->request->all();   

    //método para definir o índice pode ser alterado
    $newPostNumber = $postList['posts'][0]['AbsNum']+1;


    print("\nfilesize: ".filesize($_FILES['image']['tmp_name'])."\n");


    $imagedata = file_get_contents($_FILES['image']['tmp_name']);
    $base64Data = base64_encode($imagedata); 
    print("antes do blob");
    $post['image'] = new Blob($imageData);
    print($post['image']);
    // $myfile = fopen($_FILES['image']['tmp_name'], "rb") or die("Unable to open file!");
    // $post['image'] = stream_get_contents($myfile);
    // fclose($myfile);

    $post['AbsNum'] = $newPostNumber;
    if (!empty($post['date'])) {
        $d = new \DateTime($post['date']);
        $post['date'] = $d->setTimezone(
            new \DateTimeZone('UTC'))->format("Y-m-d\TH:i:s\Z");
    }
    print_r($post);
    $id = $model->create($post);

    return $postList['posts'][0]['AbsNum'];

});
// [END add]

// [START show]
$app->get('/posts/{id}', function ($id) use ($app) {
    /** @var DataModelInterface $model */
    $model = $app['bookshelf.model'];
    $post = $model->read($id);
    if (!$post) {
        return new Response('', Response::HTTP_NOT_FOUND);
    }
    /** @var Twig_Environment $twig */
    $twig = $app['twig'];

    return $twig->render('view.html.twig', array('post' => $post));
});
// [END show]

// [START edit]
$app->get('/posts/{id}/edit', function ($id) use ($app) {
    /** @var DataModelInterface $model */
    $model = $app['bookshelf.model'];
    $post = $model->read($id);
    if (!$post) {
        return new Response('', Response::HTTP_NOT_FOUND);
    }
    /** @var Twig_Environment $twig */
    $twig = $app['twig'];

    return $twig->render('form.html.twig', array(
        'action' => 'Edit',
        'book' => $post,
    ));
});

$app->post('/posts/{id}/edit', function (Request $request, $id) use ($app) {
    $post = $request->request->all();
    $post['id'] = $id;
    /** @var DataModelInterface $model */
    $model = $app['bookshelf.model'];
    if (!$model->read($id)) {
        return new Response('', Response::HTTP_NOT_FOUND);
    }
    if (!empty($post['date'])) {
        $d = new \DateTime($post['date']);
        $post['date'] = $d->setTimezone(
            new \DateTimeZone('UTC'))->format("Y-m-d\TH:i:s\Z");
    }
    if ($model->update($post)) {
        return $app->redirect("/posts/$id");
    }

    return new Response('Could not update book');
});
// [END edit]

// [START delete]
$app->post('/posts/{id}/delete', function ($id) use ($app) {
    /** @var DataModelInterface $model */
    $model = $app['bookshelf.model'];
    $post = $model->read($id);
    if ($post) {
        $model->delete($id);

        return $app->redirect('/posts/', Response::HTTP_SEE_OTHER);
    }

    return new Response('', Response::HTTP_NOT_FOUND);
});
// [END delete]

//teste meu///////////////////////////////////////////////////////////////////////

$app->post('/impulse/', function () use ($app){
    /** @var DataModelInterface $model */
        /** @var DataModelInterface $model */
    $model = $app['bookshelf.model'];
    $post = $model->read("5639445604728832");
    /** @var Twig_Environment $tw, there was an error uploading your fig */

    $resposta = $post['title'];

    return new Response($resposta, Response::HTTP_OK);
});
