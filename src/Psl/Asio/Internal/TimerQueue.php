<?php

declare(strict_types=1);

namespace Psl\Asio\Internal;

use Psl;
use Psl\Iter;

/**
 * Uses a binary tree stored in an array to implement a heap.
 */
final class TimerQueue
{
    /**
     * @var array<int, Watcher<int>>
     */
    private array $data = [];

    /**
     * @var int[]
     */
    private array $pointers = [];

    /**
     * @param int $node Rebuild the data array from the given node upward.
     */
    private function heapifyUp(int $node): void
    {
        $entry = $this->data[$node];
        while ($node !== 0 && $entry->expiration < $this->data[$parent = ($node - 1) >> 1]->expiration) {
            $this->swap($node, $parent);
            $node = $parent;
        }
    }

    /**
     * @param int $node Rebuild the data array from the given node downward.
     */
    private function heapifyDown(int $node): void
    {
        $length = Iter\count($this->data);
        while (($child = ($node << 1) + 1) < $length) {
            if (
                $this->data[$child]->expiration < $this->data[$node]->expiration &&
                ($child + 1 >= $length || $this->data[$child]->expiration < $this->data[$child + 1]->expiration)
            ) {
                // Left child is less than parent and right child.
                $swap = $child;
            } elseif ($child + 1 < $length && $this->data[$child + 1]->expiration < $this->data[$node]->expiration) {
                // Right child is less than parent and left child.
                $swap = $child + 1;
            } else { // Left and right child are greater than parent.
                break;
            }

            $this->swap($node, $swap);
            $node = $swap;
        }
    }

    private function swap(int $left, int $right): void
    {
        $temp = $this->data[$left];

        $this->data[$left] = $this->data[$right];
        $this->pointers[$this->data[$right]->id] = $left;

        $this->data[$right] = $temp;
        $this->pointers[$temp->id] = $right;
    }

    /**
     * Inserts the watcher into the queue. Time complexity: O(log(n)).
     *
     * @param Watcher<int> $watcher
     * 
     * @throws Psl\Exception\InvariantViolationException If $watcher does not specify expiration timestamp.
     */
    public function insert(Watcher $watcher): void
    {
        Psl\invariant($watcher->expiration !== null, '$watcher does not specify expiration timestamp.');
        Psl\invariant(!isset($this->pointers[$watcher->id]), 'invalid $watcher.');

        $node = Iter\count($this->data);
        $this->data[$node] = $watcher;
        $this->pointers[$watcher->id] = $node;

        $this->heapifyUp($node);
    }

    /**
     * Removes the given watcher from the queue. Time complexity: O(log(n)).
     *
     * @param Watcher<int> $watcher
     */
    public function remove(Watcher $watcher): void
    {
        $id = $watcher->id;

        if (!isset($this->pointers[$id])) {
            return;
        }

        $this->removeAndRebuild($this->pointers[$id]);
    }

    /**
     * Deletes and returns the Watcher on top of the heap if it has expired, otherwise null is returned.
     * Time complexity: O(log(n)).
     *
     * @param int $now Current loop time.
     *
     * @return Watcher<int>|null Expired watcher at the top of the heap or null if the watcher has not expired.
     */
    public function extract(int $now): ?Watcher
    {
        if (empty($this->data)) {
            return null;
        }

        $watcher = $this->data[0];

        if ($watcher->expiration > $now) {
            return null;
        }

        $this->removeAndRebuild(0);

        return $watcher;
    }

    /**
     * Returns the expiration time value at the top of the heap. Time complexity: O(1).
     *
     * @return int|null Expiration time of the watcher at the top of the heap or null if the heap is empty.
     */
    public function peek(): ?int
    {
        return isset($this->data[0]) ? $this->data[0]->expiration : null;
    }

    /**
     * @param int $node Remove the given node and then rebuild the data array.
     */
    private function removeAndRebuild(int $node): void
    {
        $length = Iter\count($this->data) - 1;
        $id = $this->data[$node]->id;
        $left = $this->data[$node] = $this->data[$length];
        $this->pointers[$left->id] = $node;
        unset($this->data[$length], $this->pointers[$id]);

        if ($node < $length) { // don't need to do anything if we removed the last element
            $parent = ($node - 1) >> 1;
            if ($parent >= 0 && $this->data[$node]->expiration < $this->data[$parent]->expiration) {
                $this->heapifyUp($node);
            } else {
                $this->heapifyDown($node);
            }
        }
    }
}