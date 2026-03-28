<?php

declare(strict_types=1);

namespace App\Controller;

class CommentsController extends AppController
{
    public function add($postId = null)
    {
        $comment = $this->Comments->newEmptyEntity();
        if ($this->request->is('post')) {
            $comment = $this->Comments->patchEntity($comment, $this->request->getData());
            $comment->post_id = (int)$postId;
            if ($this->Comments->save($comment)) {
                $this->Flash->success(__('コメントを追加しました。'));
            } else {
                $this->Flash->error(__('コメントの追加に失敗しました。'));
            }
        }

        return $this->redirect(['controller' => 'Posts', 'action' => 'view', $postId]);
    }

    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $comment = $this->Comments->get($id);
        $postId = $comment->post_id;
        if ($this->Comments->delete($comment)) {
            $this->Flash->success(__('コメントを削除しました。'));
        } else {
            $this->Flash->error(__('コメントの削除に失敗しました。'));
        }

        return $this->redirect(['controller' => 'Posts', 'action' => 'view', $postId]);
    }
}
