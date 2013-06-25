<?php

class ExampleTest extends TestCase
{
    public function testCreateItem()
    {
        $item = new Item;
        $item->title = "Первый товар";
        $item->save();
        $item->delete();
    }

    public function testHasManyReviews()
    {
        $review = new Item\Review;
        $review->author = 'Slava';
        $review->review = "А че, хороший товар!";

        $item = new Item;
        $item->title = "Первый товар";
        $item->save();

        $item->reviews()->save($review);

        $this->assertEquals($item->reviews()->count(), 1);

        $item->delete();
        $review->delete();
    }

    public function testCannotCreateReviewWithEmptyAuthor()
    {
        $review = new Item\Review;
        $this->assertEquals(false, $review->save());

        $review = new Item\Review;
        $review->author = "test";
        $this->assertEquals(true, $review->save());

        $review->delete();
    }


    public function testShowItem()
    {
        $item = new Item;
        $item->title = "Первый товар";
        $item->save();

        $response = $this->call('GET', 'item/' . $item->id);
        $this->assertResponseOk();

        $item->delete();
    }

    /**
     * @expectedException Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function testShowNonExistingItem()
    {
        $response = $this->call('GET', 'item/123e');
        $this->assertResponseOk();
    }

    public function testItemShowsName()
    {
        $item = Item::create(array('title' => 'Название товара'));
        $crawler = $this->client->request('GET', '/item/' . $item->id);
        $this->assertTrue($this->client->getResponse()->isOk());
        $this->assertCount(1, $crawler->filter('h1:contains("Название товара")'));

        $item->delete();
    }

    public function testItemShowsNameSafely()
    {
        $item = Item::create(array('title' => 'Название товара <>'));
        $this->client->request('GET', '/item/' . $item->id);
        $this->assertContains('<h1>Название товара &lt;&gt;</h1>', $this->client->getResponse()->getContent());

        $item->delete();
    }


    public function testItemHasReviewForm()
    {
        $item = Item::create(array('title' => 'Название товара'));
        $crawler = $this->client->request('GET', '/item/' . $item->id);
        $this->assertTrue($this->client->getResponse()->isOk());
        $form = $crawler->filter('form');
        $this->assertCount(1, $form);

        $this->assertStringEndsWith("/item/{$item->id}/review", $form->attr('action'));
        $this->assertEquals("POST", $form->attr('method'));

        $this->assertCount(1, $form->filter("input[name='author']"));
        $this->assertCount(1, $form->filter("textarea[name='review']"));

        $item->delete();
    }

    public function testItemReviewFormSavesForm()
    {
        $item = Item::create(array('title' => 'Название товара'));

        $count = Item\Review::count();

        $get = array('author' => 'Slava', 'review' => "А че, отличный < товарчик > !",
            'status' => Item\Review::PUBLISHED);
        $crawler = $this->call('POST', "/item/{$item->id}/review", $get);

        $this->assertEquals($count + 1, Item\Review::count());

        $this->assertEquals(1, $item->reviews()->count());

        $review = $item->reviews()->first();
        $this->assertEquals("А че, отличный < товарчик > !", $review->review);
        $this->assertEquals("Slava", $review->author);
        $this->assertEquals("draft", $review->status);

        $this->assertRedirectedTo("/item/{$item->id}");

        $this->call('GET', "/item/{$item->id}");
        $this->assertContains("Спасибо за отзыв", $this->client->getResponse()->getContent(), "Flash не передался");

        // Похоже это баг Laravel, что Flash не забывается между запросами в тестах (в реальном режиме работает ОК)
        Session::forget('thanks');

        $this->call('GET', "/item/{$item->id}");
        $this->assertNotContains("Спасибо за отзыв", $this->client->getResponse()->getContent(), "Flash показался второй раз");

        $item->delete();
        $review->delete();
    }

    public function testShowsPublishedReviews()
    {
        $item = new Item;
        $item->title = "Первый товар";
        $item->save();

        $review = new Item\Review;
        $review->author = 'Slava';
        $review->review = "А че, хороший < товар!";
        $review->status = Item\Review::PUBLISHED;
        $item->reviews()->save($review);

        $review2 = new Item\Review;
        $review2->author = 'BadAuthor';
        $review2->review = "Отзыв не должен выводится";
        $review2->status = Item\Review::DRAFT;
        $item->reviews()->save($review2);

        $crawler = $this->client->request('GET', "/item/{$item->id}");

        $this->assertNotContains('Отзыв не должен выводится', $crawler->filter('.reviews')->text(), "Выводится отзыв со статусом DRAFT");

        $this->assertCount(1, $crawler->filter('.item-review>.review:contains("А че, хороший")'), 'Не выводится текст отзыва');
        $this->assertCount(1, $crawler->filter('.item-review>.review:contains("А че, хороший < товар!")'), 'XSS injection!');
        $this->assertCount(1, $crawler->filter('.item-review>.author:contains("Slava")'), 'Не выводится имя автора');

        $item->delete();
        $review->delete();
    }


}