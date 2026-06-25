/**
 * Havenlytics Checkbox Repeater Field - Simple List Version with Arrow Buttons
 * @package Havenlytics
 * @version 2.0.0
 */

(function($) {
    'use strict';

    class HavenlyticsCheckboxRepeaterField {
        constructor() {
            this.init();
        }

        init() {
            // Event handlers
            $(document).on('click', '.hvnly-checkbox-repeater-add-item', this.addItem.bind(this));
            $(document).on('click', '.hvnly-checkbox-repeater-remove-item', this.removeItem.bind(this));
            $(document).on('click', '.hvnly-checkbox-repeater-move-up', this.moveItemUp.bind(this));
            $(document).on('click', '.hvnly-checkbox-repeater-move-down', this.moveItemDown.bind(this));
            $(document).on('input', '.hvnly-checkbox-repeater-field input[type="text"]', this.updateHiddenField.bind(this));
            
            // Initialize button states
            this.updateButtonStates();
        }

        addItem(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $field = $button.closest('.hvnly-checkbox-repeater-field');
            const $itemsContainer = $field.find('.hvnly-checkbox-repeater-items');
            const $lastItem = $itemsContainer.find('.hvnly-checkbox-repeater-item:last');
            const itemIndex = $lastItem.length ? parseInt($lastItem.data('item-index')) + 1 : 0;
            
            // Clone the first item
            const $firstItem = $itemsContainer.find('.hvnly-checkbox-repeater-item:first');
            const $newItem = $firstItem.clone();
            
            // Update item index and clear value
            $newItem.attr('data-item-index', itemIndex);
            
            // Update input name and clear value
            $newItem.find('input[type="text"]').each(function() {
                const $input = $(this);
                const name = $input.attr('name').replace(/\[\d+\]/, `[${itemIndex}]`);
                $input.attr('name', name).val('');
            });
            
            // Add the new item
            $itemsContainer.append($newItem);
            
            // Update button states
            this.updateButtonStates();
            
            // Update the hidden field
            this.updateHiddenField();
        }

        removeItem(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $item = $button.closest('.hvnly-checkbox-repeater-item');
            const $field = $button.closest('.hvnly-checkbox-repeater-field');
            const $itemsContainer = $field.find('.hvnly-checkbox-repeater-items');
            
            // Don't remove if it's the only item
            if ($itemsContainer.find('.hvnly-checkbox-repeater-item').length <= 1) {
                return;
            }
            
            // Remove the item
            $item.remove();
            
            // Update item indexes
            this.updateItemIndexes();
            
            // Update button states
            this.updateButtonStates();
            
            // Update the hidden field
            this.updateHiddenField();
        }

        moveItemUp(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $item = $button.closest('.hvnly-checkbox-repeater-item');
            const $prevItem = $item.prev('.hvnly-checkbox-repeater-item');
            
            // Can't move up if it's already the first item
            if (!$prevItem.length) {
                return;
            }
            
            // Move the item up
            $item.insertBefore($prevItem);
            
            // Update item indexes
            this.updateItemIndexes();
            
            // Update button states
            this.updateButtonStates();
            
            // Update the hidden field
            this.updateHiddenField();
        }

        moveItemDown(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $item = $button.closest('.hvnly-checkbox-repeater-item');
            const $nextItem = $item.next('.hvnly-checkbox-repeater-item');
            
            // Can't move down if it's already the last item
            if (!$nextItem.length) {
                return;
            }
            
            // Move the item down
            $item.insertAfter($nextItem);
            
            // Update item indexes
            this.updateItemIndexes();
            
            // Update button states
            this.updateButtonStates();
            
            // Update the hidden field
            this.updateHiddenField();
        }

        updateButtonStates() {
            $('.hvnly-checkbox-repeater-items').each(function() {
                const $itemsContainer = $(this);
                const $items = $itemsContainer.find('.hvnly-checkbox-repeater-item');
                
                $items.each(function(index) {
                    const $item = $(this);
                    const $upButton = $item.find('.hvnly-checkbox-repeater-move-up');
                    const $downButton = $item.find('.hvnly-checkbox-repeater-move-down');
                    
                    // Disable up button for first item
                    if (index === 0) {
                        $upButton.prop('disabled', true);
                    } else {
                        $upButton.prop('disabled', false);
                    }
                    
                    // Disable down button for last item
                    if (index === $items.length - 1) {
                        $downButton.prop('disabled', true);
                    } else {
                        $downButton.prop('disabled', false);
                    }
                });
            });
        }

        updateItemIndexes() {
            $('.hvnly-checkbox-repeater-items').each(function() {
                const $itemsContainer = $(this);
                
                $itemsContainer.find('.hvnly-checkbox-repeater-item').each(function(index) {
                    const $item = $(this);
                    
                    // Update item index attribute
                    $item.attr('data-item-index', index);
                    
                    // Update input names
                    $item.find('input[type="text"]').each(function() {
                        const $input = $(this);
                        const name = $input.attr('name').replace(/\[\d+\]/, `[${index}]`);
                        $input.attr('name', name);
                    });
                });
            });
            
            // Update the hidden field after reordering
            this.updateHiddenField();
        }

        updateHiddenField() {
            $('.hvnly-checkbox-repeater-field').each(function() {
                const $field = $(this);
                const $hiddenField = $field.find('.hvnly-checkbox-repeater-hidden');
                const items = [];
                
                $field.find('.hvnly-checkbox-repeater-item').each(function() {
                    const $item = $(this);
                    const value = $item.find('input[type="text"]').val();
                    
                    if (value) {
                        items.push(value);
                    }
                });
                
                $hiddenField.val(JSON.stringify(items));
            });
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        new HavenlyticsCheckboxRepeaterField();
    });

})(jQuery);