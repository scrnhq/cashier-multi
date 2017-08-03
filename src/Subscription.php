<?php

namespace ScrnHQ\Cashier;

use Laravel\Cashier\Subscription as BaseSubscription;
use LogicException;

class Subscription extends BaseSubscription
{
    /**
     * Get the items related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany(SubscriptionItem::class)->orderBy('created_at', 'desc');
    }

    public function getStripePlanAttribute()
    {
        return $this->getSingleSubscriptionItem()->stripe_plan;
    }

    /**
     *
     * @deprecated Access must me made to individual items.
     * @return int
     */
    public function getQuantityAttribute()
    {
        return $this->getSingleSubscriptionItem()->quantity;
    }

    /**
     * Update the quantity of the subscription.
     *
     * @deprecated Updates must be made to individual items.
     * @param int $quantity
     * @param null $customer
     * @return $this
     */
    public function updateQuantity($quantity, $customer = null)
    {
        $item = $this->getSingleSubscriptionItem();

        $stripeItem = $item->asStripeSubscriptionItem();
        $stripeItem->quantity = $quantity;
        $stripeItem->prorate = $this->prorate;
        $stripeItem->save();

        $item->quantity = $quantity;
        $item->save();

        return $this;
    }

    /**
     * Swap the subscription to a new Stripe plan.
     *
     * @deprecated Updates must be made to individual items.
     * @param $plan
     * @return $this
     */
    public function swap($plan)
    {
        $item = $this->getSingleSubscriptionItem();

        $subscription = $this->asStripeSubscription();

        $stripeItem = $item->asStripeSubscriptionItem();

        $stripeItem->plan = $plan;

        $stripeItem->prorate = $this->prorate;

        if (! is_null($this->billingCycleAnchor)) {
            $subscription->billingCycleAnchor = $this->billingCycleAnchor;
        }

        // If no specific trial end date has been set, the default behavior should be
        // to maintain the current trial state, whether that is "active" or to run
        // the swap out with the exact number of days left on this current plan.
        if ($this->onTrial()) {
            $subscription->trial_end = $this->trial_ends_at->getTimestamp();
        } else {
            $subscription->trial_end = 'now';
        }

        // Again, if no explicit quantity was set, the default behaviors should be to
        // maintain the current quantity onto the new plan. This is a sensible one
        // that should be the expected behavior for most developers with Stripe.
        if ($item->quantity) {
            $stripeItem->quantity = $item->quantity;
        }

        $subscription->save();
        $stripeItem->save();

        $this->user->invoice();

        $item->fill([
            'stripe_plan' => $plan,
        ])->save();

        return $this;
    }

    /**
     * Add a plan to the subscription.
     *
     * @param $plan
     * @param int $quantity
     * @param bool $prorate
     * @return $this
     */
    public function addItem($plan, $quantity = 1, $prorate = true)
    {
        $subscription = $this->asStripeSubscription();

        $subscriptionItem = $subscription->items->create([
            'plan' => $plan,
            'prorate' => $prorate,
            'quantity' => $quantity,
        ]);

        $this->items()->create([
            'stripe_id' => $subscriptionItem->id,
            'stripe_plan' => $plan,
            'quantity' => $quantity,
        ]);

        return $this;
    }

    /**
     * Remove a plan from the subscription.
     *
     * @param $plan
     * @param bool $prorate
     * @return $this
     */
    public function removeItem($plan, $prorate = true)
    {
        $item = $this->getItem($plan);

        if (is_null($item)) {
            return $this;
        }

        $item = $item->asStripeSubscriptionItem();

        $item->delete([
            'prorate' => $prorate,
        ]);

        $this->items()->where('stripe_plan', $plan)->delete();

        return $this;
    }

    /**
     * Determine if the subscription has the given plan.
     *
     * @param $plan
     * @return bool
     */
    public function hasItem($plan)
    {
        return $this->items()->where('stripe_plan', $plan)->count() > 0;
    }

    /**
     * Get the subscription item if there is only one item attached.
     *
     * @return SubscriptionItem
     */
    public function getSingleSubscriptionItem()
    {
        if (count($this->items) !== 1) {
            throw new LogicException('Cannot retrieve the single subscription item if there are multiple plans attached.');
        }
        return $this->items()->first();
    }

    /**
     * Get the items related to the subscription.
     *
     * @return SubscriptionItem
     */
    public function getItem($plan)
    {
        return $this->items()->where('stripe_plan', $plan)->firstOrFail();
    }

    /**
     * Resume the cancelled subscription.
     *
     * @return $this
     *
     * @throws \LogicException
     */
    public function resume()
    {
        if (! $this->onGracePeriod()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }

        $subscription = $this->asStripeSubscription();

        if ($this->onTrial()) {
            $subscription->trial_end = $this->trial_ends_at->getTimestamp();
        } else {
            $subscription->trial_end = 'now';
        }

        $subscription->save();

        // Finally, we will remove the ending timestamp from the user's record in the
        // local database to indicate that the subscription is active again and is
        // no longer "cancelled". Then we will save this record in the database.
        $this->fill(['ends_at' => null])->save();

        return $this;
    }
}