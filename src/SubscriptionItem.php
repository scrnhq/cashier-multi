<?php

namespace ScrnHQ\Cashier;

use Illuminate\Database\Eloquent\Model;

class SubscriptionItem extends Model
{
    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * Get the subscription that contains this item.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Increment the quantity of the subscription item.
     *
     * @param int $count
     * @return $this
     */
    public function incrementQuantity($count = 1)
    {
        $this->updateQuantity($this->quantity + $count);

        return $this;
    }

    /**
     *  Increment the quantity of the subscription item, and invoice immediately.
     *
     * @param int $count
     * @return $this
     */
    public function incrementAndInvoice($count = 1)
    {
        $this->incrementQuantity($count);

        $this->subscription->user->invoice();

        return $this;
    }

    /**
     * Decrement the quantity of the subscription item.
     *
     * @param int $count
     * @return $this
     */
    public function decrementQuantity($count = 1)
    {
        $this->updateQuantity(max(1, $this->quantity - $count));

        return $this;
    }

    /**
     * Update the quantity of the subscription item.
     *
     * @param int $quantity
     * @return $this
     */
    public function updateQuantity($quantity)
    {
        $subscriptionItem = $this->asStripeSubscriptionItem();

        $subscriptionItem->quantity = $quantity;

        $subscriptionItem->save();

        $this->quantity = $quantity;

        $this->save();

        return $this;
    }

    /**
     * Get the subscription item as a Stripe subscription object.
     *
     * @return \Stripe\SubscriptionItem
     */
    public function asStripeSubscriptionItem()
    {
        $subscription = $this->subscription->asStripeSubscription();

        $subscription->items->url = substr($subscription->items->url, 0, strpos($subscription->items->url, '?'));

        return $subscription->items->retrieve($this->stripe_id);
    }
}