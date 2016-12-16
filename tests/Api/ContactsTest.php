<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     MIT http://opensource.org/licenses/MIT
 */

namespace Mautic\Tests\Api;

use Mautic\Api\Contacts;

class ContactsTest extends MauticApiTestCase
{
    protected $skipPayloadAssertion = array('firstname', 'lastname', 'tags');

    public function setUp()
    {
        $this->api = $this->getContext('contacts');
        $this->testPayload = array(
            'firstname' => 'test',
            'lastname'  => 'test',
            'points'    => 3,
            'tags'      => array(
                'APItag1',
                'APItag2',
            )
        );
    }

    protected function assertEventResponse($response, $expectedEvents = array())
    {
        $this->assertErrors($response);
        $this->assertTrue(isset($response['events']));
        $this->assertTrue(isset($response['total']));
        $this->assertTrue(isset($response['types']));
        $this->assertTrue(isset($response['order']));
        $this->assertTrue(isset($response['filters']));
        $this->assertEquals(count($response['events']), count($expectedEvents));

        foreach ($expectedEvents as $key => $event) {
            $this->assertEquals($response['events'][$key]['event'], $event);
        }
    }

    public function testGetList()
    {
        $this->standardTestGetList();
    }

    public function testGetListOfSpecificIds()
    {
        $this->standardTestGetListOfSpecificIds();
    }

    public function testGetListOfSpecificSegment()
    {
        
        $segmentApi = $this->getContext('segments');

        // Create Segment
        $segmentPayload = array(
            'name' => 'Contact Segment Search API test'
        );
        $response = $segmentApi->create($segmentPayload);
        $this->assertErrors($response);
        $segmentId = $response['list']['id'];
        $segmentAlias = $response['list']['alias'];

        $itemIds = array();
        for ($i = 0; $i <= 2; $i++) {

            // Create some items
            $response = $this->api->create($this->testPayload);
            $this->assertErrors($response);
            $itemIds[] = $response[$this->api->itemName()]['id'];

            // Add contacts to the segment
            $response = $segmentApi->addContact($segmentId, $response[$this->api->itemName()]['id']);
            $this->assertErrors($response);
        }

        $search = 'segment:'.$segmentAlias;
        $response = $this->api->getList($search);
        $this->assertErrors($response);
        $this->assertEquals(count($itemIds), $response['total']);

        foreach ($response[$this->api->listName()] as $item) {
            $this->assertTrue(in_array($item['id'], $itemIds));
            $this->api->delete($item['id']);
            $this->assertErrors($response);
        }

        $segmentApi->delete($segmentId);
        $this->assertErrors($response);
    }

    public function testGetFieldList()
    {
        $response    = $this->api->getFieldList();
        $this->assertErrors($response);
        $this->assertGreaterThan(0, count($response));
    }

    public function testGetSegmentsList()
    {
        $response    = $this->api->getSegments();
        $this->assertErrors($response);
    }

    public function testGetEvents()
    {
        $response = $this->api->create($this->testPayload);
        $this->assertErrors($response);
        $contact = $response[$this->api->itemName()];

        $response = $this->api->getEvents($contact['id']);
        $this->assertEventResponse($response, array('lead.create', 'lead.identified'));

        $response = $this->api->delete($contact['id']);
        $this->assertErrors($response);
    }

    public function testGetEventsAdvanced()
    {
        $response = $this->api->create($this->testPayload);
        $this->assertErrors($response);
        $contact = $response[$this->api->itemName()];

        $response = $this->api->getEvents($contact['id'], '', array('lead.identified'));
        $this->assertEventResponse($response, array('lead.identified'));

        $response = $this->api->delete($contact['id']);
        $this->assertErrors($response);
    }

    public function testCreateGetAndDelete()
    {
        // Test Create
        $response = $this->api->create($this->testPayload);
        $this->assertPayload($response);
        $this->assertEquals(count($response[$this->api->itemName()]['tags']), count($this->testPayload['tags']));

        // Test Get
        $response = $this->api->get($response[$this->api->itemName()]['id']);
        $this->assertPayload($response);

        // Test Delete
        $response = $this->api->delete($response[$this->api->itemName()]['id']);
        $this->assertErrors($response);
    }

    public function testDncAddInCreate()
    {
        // Add DNC to the payload
        $this->testPayload['doNotContact'] = array(
            array(
                'channel' => 'email',
                'reason' => Contacts::BOUNCED,
            )
        );

        $response = $this->api->create($this->testPayload);
        $this->assertErrors($response);
        $this->assertEquals(count($response[$this->api->itemName()]['doNotContact']), 1);

        $response = $this->api->delete($response[$this->api->itemName()]['id']);
        $this->assertErrors($response);
    }

    public function testDncAddRemoveEndpoints()
    {
        $response = $this->api->create($this->testPayload);
        $this->assertErrors($response);

        // Test Add
        $response = $this->api->addDnc($response[$this->api->itemName()]['id'], 'email', Contacts::BOUNCED);
        $this->assertErrors($response);
        $this->assertEquals(count($response[$this->api->itemName()]['doNotContact']), 1);

        // Test Remove
        $response = $this->api->removeDnc($response[$this->api->itemName()]['id'], $response[$this->api->itemName()]['doNotContact'][0]['channel']);
        $this->assertErrors($response);

        $response = $this->api->delete($response[$this->api->itemName()]['id']);
        $this->assertErrors($response);
    }

    public function testEditPatch()
    {
        $pointsSet   = 5;
        $response    = $this->api->edit(10000, $this->testPayload);

        //there should be an error as the contact shouldn't exist
        $this->assertTrue(isset($response['error']), $response['error']['message']);

        $response = $this->api->create($this->testPayload);
        $this->assertErrors($response);

        $response = $this->api->edit(
            $response[$this->api->itemName()]['id'],
            array(
                'firstname' => 'test2',
                'lastname'  => 'test2',
                'points'    => $pointsSet,
            )
        );

        $this->assertErrors($response);
        $this->assertSame($response[$this->api->itemName()]['points'], $pointsSet, 'Points were not set correctly');

        //now delete the contact
        $response = $this->api->delete($response[$this->api->itemName()]['id']);
        $this->assertErrors($response);
    }

    public function testEditPatchFormError()
    {
        $response = $this->api->create($this->testPayload);
        $this->assertErrors($response);

        $response = $this->api->edit(
            $response[$this->api->itemName()]['id'],
            array(
                'country' => 'not existing country'
            )
        );

        //there should be an error as the country does not exist
        $this->assertTrue(isset($response['error']), $response['error']['message']);
    }

    public function testEditPut()
    {
        $this->standardTestEditPut();
    }

    public function testAddPoints()
    {
        $pointToAdd = 5;

        $response = $this->api->create($this->testPayload);
        $this->assertErrors($response);
        $contact = $response[$this->api->itemName()];

        $response = $this->api->addPoints($contact['id'], $pointToAdd);
        $this->assertErrors($response);
        $this->assertTrue(!empty($response['success']), 'Adding point to a contact with ID ='.$contact['id'].' was not successful');

        $response = $this->api->get($contact['id']);
        $this->assertErrors($response);
        $this->assertSame($response[$this->api->itemName()]['points'], ($contact['points'] + $pointToAdd), 'Points were not added correctly');

        $response = $this->api->delete($contact['id']);
        $this->assertErrors($response);
    }

    public function testSubtractPoints()
    {
        $pointToSub = 5;
        
        $response = $this->api->create($this->testPayload);
        $this->assertErrors($response);
        $contact = $response[$this->api->itemName()];

        $response = $this->api->subtractPoints($contact['id'], $pointToSub);
        $this->assertErrors($response);
        $this->assertTrue(!empty($response['success']), 'Subtracting point to a contact with ID ='.$contact['id'].' was not successful');

        $response = $this->api->get($contact['id']);
        $this->assertErrors($response);
        $this->assertSame($response[$this->api->itemName()]['points'], ($contact['points'] - $pointToSub), 'Points were not subtracted correctly');

        $response = $this->api->delete($contact['id']);
        $this->assertErrors($response);
    }
}
