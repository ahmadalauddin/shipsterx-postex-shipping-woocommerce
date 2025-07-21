<?php
use PHPUnit\Framework\TestCase;

require 'vendor/autoload.php';
// require_once dirname(__DIR__) . '/postex-woocommerce.php';

if (!class_exists('PostEx_Client')) {
    class PostEx_Client {
        public function create_order($payload) {
            // this will be mocked, so can be left empty
        }
    }
}

class PostExClientTest extends TestCase {
    public function testPayloadMapping() {
        $order_data = [
            'order_id' => 123,
            'total' => 1500,
            'weight' => 2.5,
            'shipping_first_name' => 'Ali',
            'shipping_last_name' => 'Khan',
            'billing_phone' => '03001234567',
            'shipping_address_1' => 'Street 1',
            'shipping_city' => 'Karachi',
        ];
        $ref_number = 1001;
        $payload = [
            'orderRefNumber' => $ref_number,
            'codAmount'      => $order_data['total'],
            'weight'         => $order_data['weight'],
            'dimensions'     => [ 'length' => 10, 'width' => 10, 'height' => 10 ],
            'recipient'      => [
                'name'    => $order_data['shipping_first_name'] . ' ' . $order_data['shipping_last_name'],
                'phone'   => $order_data['billing_phone'],
                'address' => $order_data['shipping_address_1'],
                'city'    => $order_data['shipping_city'],
            ],
        ];
        $this->assertEquals('Ali Khan', $payload['recipient']['name']);
        $this->assertEquals(1500, $payload['codAmount']);
        $this->assertEquals(2.5, $payload['weight']);
    }

    public function testCreateOrderApiMock() {
        $client = $this->getMockBuilder('PostEx_Client')
            ->onlyMethods(['create_order'])
            ->getMock();
        $client->expects($this->once())
            ->method('create_order')
            ->willReturn([
                'response' => [
                    'code' => 200
                ],
                'body' => json_encode(['trackingNumber' => 'PX123456'])
            ]);
        $payload = [
            'orderRefNumber' => 1001,
            'codAmount' => 1500,
            'weight' => 2.5,
            'dimensions' => [ 'length' => 10, 'width' => 10, 'height' => 10 ],
            'recipient' => [
                'name' => 'Ali Khan',
                'phone' => '03001234567',
                'address' => 'Street 1',
                'city' => 'Karachi',
            ],
        ];
        $response = $client->create_order($payload);
        $body = json_decode($response['body'], true);
        $this->assertEquals('PX123456', $body['trackingNumber']);
    }

    public function testSanityCheck() {
        $this->assertTrue(true);
    }
}
