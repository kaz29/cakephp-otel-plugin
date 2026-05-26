<?php

declare(strict_types=1);

namespace App\Controller;

class PostsController extends AppController
{
    public function index()
    {
        $posts = $this->paginate($this->Posts, [
            'order' => ['Posts.created' => 'DESC'],
            'limit' => 10,
        ]);
        $this->set(compact('posts'));
    }

    public function view($id = null)
    {
        $post = $this->Posts->get($id, contain: ['Comments']);
        $this->set(compact('post'));
    }

    public function add()
    {
        $post = $this->Posts->newEmptyEntity();
        if ($this->request->is('post')) {
            $post = $this->Posts->patchEntity($post, $this->request->getData());
            if ($this->Posts->save($post)) {
                $this->Flash->success(__('投稿を保存しました。'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('投稿の保存に失敗しました。'));
        }
        $this->set(compact('post'));
    }

    public function edit($id = null)
    {
        $post = $this->Posts->get($id);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $post = $this->Posts->patchEntity($post, $this->request->getData());
            if ($this->Posts->save($post)) {
                $this->Flash->success(__('投稿を更新しました。'));

                return $this->redirect(['action' => 'view', $id]);
            }
            $this->Flash->error(__('投稿の更新に失敗しました。'));
        }
        $this->set(compact('post'));
    }

    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $post = $this->Posts->get($id);
        if ($this->Posts->delete($post)) {
            $this->Flash->success(__('投稿を削除しました。'));
        } else {
            $this->Flash->error(__('投稿の削除に失敗しました。'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
