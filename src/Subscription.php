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
    public function subscriptionItems()
    {
        return $this->hasMany(SubscriptionItem::class)->orderBy('created_at', 'desc');
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
        trigger_error("Deprecation error. Updates should be performed on subscription items.", E_USER_NOTICE);
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
        trigger_error("Deprecation error. Updates should be performed on subscription items.", E_USER_NOTICE);
        return $this;
    }

    /**
     * Adds
     * @param $plan
     * @param int $quantity
     * @param bool $prorate
     */
    public function addItem($plan, $quantity = 1, $prorate = true)
    {
        $subscription = $this->asStripeSubscription();

        $subscriptionItem = $subscription->items->create([
            'plan' => $plan,
            'prorate' => $prorate,
            'quantity' => $quantity,
        ]);

        $this->subscriptionItems()->create([
            'stripe_id' => $subscriptionItem->id,
            'stripe_plan' => $plan,
            'quantity' => $quantity,
        ]);
    }

    /**
     * @param $plan
     * @param bool $prorate
     * @return $this
     */
    public function removeItem($plan, $prorate = true)
    {
        $item = $this->subscriptionItems()->where('stripe_plan', $plan)->first();

        if (is_null($item)) {
            return $this;
        }

        $item = $item->asStripeSubscriptionItem();

        $item->delete([
            'prorate' => $prorate,
        ]);

        $this->subscriptionItems()->where('stripe_plan', $plan)->delete();

        return $this;
    }

    /**
     * Determine if the subscription is on the given plan.
     *
     * @param $plan
     * @return bool
     */
    public function hasItem($plan)
    {
        return !!$this->subscriptionItems()->where('stripe_plan', $plan)->first();
    }

    /**
     * Get the items related to the subscription.
     *
     * @return SubscriptionItem
     */
    public function getItem($plan)
    {
        return $this->subscriptionItems()->where('stripe_plan', $plan)->first();
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