<?php

namespace App\Tests\Billing;

use App\Apps\Billing\Models\Subscription;
use App\Tests\Support\BuildsCustomerRegistrationData;
use App\Tests\Support\SeedsBillingSchema;
use App\Utilities\BillingManager;
use App\Utilities\DatabaseManager;
use PHPUnit\Framework\TestCase;

class BillingManagerTest extends TestCase
{
    use SeedsBillingSchema;
    use BuildsCustomerRegistrationData;

    private int $customerId;
    private int $subscriptionId;

    protected function setUp(): void
    {
        DatabaseManager::reset();
        $pdo = DatabaseManager::connect(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->seedBillingSchema($pdo);

        $_SESSION = [];
        $result = $this->registerCustomer();
        $this->customerId = $result['customer']->id;
        $this->subscriptionId = $result['subscription']->id;
    }

    public function test_generate_invoice_creates_an_unpaid_invoice(): void
    {
        $invoice = BillingManager::generateInvoice($this->customerId, $this->subscriptionId, 2000.00);

        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame(2000.00, $invoice->amount);
    }

    public function test_mark_paid_updates_the_invoice_and_activates_the_subscription(): void
    {
        $invoice = BillingManager::generateInvoice($this->customerId, $this->subscriptionId, 2000.00);

        $result = BillingManager::markPaid($invoice->id);

        $this->assertTrue($result);

        $invoices = BillingManager::invoicesForCustomer($this->customerId);
        $this->assertSame('paid', $invoices[0]->status);
        $this->assertNotNull($invoices[0]->paid_at);

        $this->assertSame('active', Subscription::find($this->subscriptionId)?->status);
    }

    public function test_mark_paid_returns_false_for_an_unknown_invoice(): void
    {
        $this->assertFalse(BillingManager::markPaid(999));
    }
}
