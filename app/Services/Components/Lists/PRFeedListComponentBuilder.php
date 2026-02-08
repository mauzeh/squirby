<?php

namespace App\Services\Components\Lists;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * PR Feed List Component Builder
 * 
 * Builds a list of PR feed items displaying personal records from all users
 */
class PRFeedListComponentBuilder
{
    protected array $data;
    
    public function __construct()
    {
        $this->data = [
            'items' => [],
            'paginator' => null,
            'emptyMessage' => 'No PRs logged yet. Be the first!',
        ];
    }
    
    /**
     * Add a single PR item to the feed
     * 
     * @param array $item PR data with keys: user_name, exercise_name, pr_type, value, rep_count, weight, previous_value, achieved_at
     * @return self
     */
    public function item(array $item): self
    {
        $this->data['items'][] = $item;
        return $this;
    }
    
    /**
     * Add multiple PR items to the feed
     * 
     * @param array $items Array of PR data
     * @return self
     */
    public function items(array $items): self
    {
        foreach ($items as $item) {
            $this->item($item);
        }
        return $this;
    }
    
    /**
     * Set the paginator for the feed
     * 
     * @param LengthAwarePaginator $paginator
     * @return self
     */
    public function paginator(LengthAwarePaginator $paginator): self
    {
        $this->data['paginator'] = $paginator;
        
        // Extract items from paginator
        $this->data['items'] = $paginator->items();
        
        return $this;
    }
    
    /**
     * Set the empty state message
     * 
     * @param string $message
     * @return self
     */
    public function emptyMessage(string $message): self
    {
        $this->data['emptyMessage'] = $message;
        return $this;
    }
    
    /**
     * Build the component array
     * 
     * @return array
     */
    public function build(): array
    {
        return [
            'type' => 'pr-feed-list',
            'data' => $this->data
        ];
    }
}
