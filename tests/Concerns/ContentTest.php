<?php

namespace Blomstra\Spam\Tests\Concerns;

use Blomstra\Spam\Concerns\Content;
use Blomstra\Spam\Filter;
use Blomstra\Spam\Tests\TestCase;
use Flarum\Foundation\Config;
use Laminas\Diactoros\Uri;

class ContentTest extends TestCase
{
    use Content;

    /**
     * @test
     * @covers \Blomstra\Spam\Filter::getAcceptableDomains
     */
    function contains_config_url()
    {
        /** @var Config $config */
        $config = resolve(Config::class);

        $domains = Filter::getAcceptableDomains();

        $this->assertContains($config->url()->getHost(), $domains);
    }

    /**
     * @covers \Blomstra\Spam\Concerns\Content::containsProblematicLinks
     * @test
     */
    function allows_reasonable_content()
    {
        $this->assertFalse(
            $this->containsProblematicContent('hello')
        );
        $this->assertFalse(
            $this->containsProblematicContent(<<<EOM
Hi there,

Have some questions.
EOM
)
        );
    }

    /**
     * @covers \Blomstra\Spam\Concerns\Content::containsProblematicLinks
     * @test
     */
    function fails_on_link()
    {
        $this->assertTrue(
            $this->containsProblematicContent(
                'https://spamlink.com'
            )
        );
        $this->assertTrue(
            $this->containsProblematicContent(<<<EOM
Hi,

https://spamlink.com is the best!
EOM
)
        );
        $this->assertTrue(
            $this->containsProblematicContent(<<<EOM
Hi,

[this](https://spamlink.com) is the best!
EOM
            )
        );
    }

    /**
     * @test
     *      * @covers \Blomstra\Spam\Concerns\Content::containsProblematicLinks
     */
    function fails_with_one_allowed_domain()
    {
        (new Filter)
            ->allowLinksFromDomain('acceptable-domain.com');

        $this->assertTrue(
            $this->containsProblematicContent(<<<EOM
Come on, [this](https://acceptable-domain.com) is the worst! [this](https://spamlink.com) is the best!
EOM
            )
        );
    }

    /**
     * @covers \Blomstra\Spam\Concerns\Content::containsProblematicLinks
     * @test
     */
    function fails_on_emails()
    {
        $this->assertTrue(
            $this->containsProblematicContent(
                'test@gmail.com'
            )
        );
        $this->assertTrue(
            $this->containsProblematicContent(<<<EOM
Hi,

test@gmail.com is the best!
EOM
            )
        );
        $this->assertTrue(
            $this->containsProblematicContent(<<<EOM
Hi,

[this](test@gmail.com) is the best!
EOM
            )
        );
    }

    /**
     * @covers \Blomstra\Spam\Concerns\Content::containsProblematicLinks
     * @test
     */
    function allows_links_with_acceptable_domain()
    {
        (new Filter)
            ->allowLinksFromDomain('acceptable-domain.com');

        $this->assertFalse(
            $this->containsProblematicContent(
                'https://acceptable-domain.com'
            )
        );
        $this->assertFalse(
            $this->containsProblematicContent(<<<EOM
Hi,

https://acceptable-domain.com is the best!
EOM
            )
        );
        $this->assertFalse(
            $this->containsProblematicContent(<<<EOM
Hi,

[this](https://acceptable-domain.com) is the best!
EOM
            )
        );
        $this->assertFalse(
            $this->containsProblematicContent(<<<EOM
Hi,

[this](https://some.acceptable-domain.com) is the best!
EOM
            )
        );
        $this->assertFalse(
            $this->containsProblematicContent(<<<EOM
Hi,

[this](https://even.some.acceptable-domain.com) is the best!
EOM
            )
        );
    }

    /**
     * @test
     * @covers \Blomstra\Spam\Concerns\Content::containsAlternateLanguage
     */
    function allows_installed_languages()
    {
        $this->assertFalse(
            $this->containsProblematicContent(
                <<<EOM
I created my profile on August 27th 2015. You won't believe it, but it's true.
EOM
            ), 'Falsely marks English as invalid language'
        );

        // Dutch
        $this->assertFalse(
            $this->containsProblematicContent(
                <<<EOM
Ik heb mijn gebruikersprofiel aangemaakt op 27 augustus 2015. Je zult het niet geloven, maar het is echt waar.
EOM
            ), 'Falsely marks Dutch as invalid language'
        );
    }

    /**
     * @test
     * @covers \Blomstra\Spam\Concerns\Content::containsAlternateLanguage
     */
    function fails_for_other_languages()
    {
        // German
        $this->assertTrue(
            $this->containsProblematicContent(
                <<<EOM
Ich habe mein account erstellt am 27er August 2015. Du kannst es bestimmt nicht glauben, aber es ist wirklich war.
EOM
            )
        );

        // Chinese simplified
        $this->assertTrue(
            $this->containsProblematicContent(
                <<<EOM
我在 2015 年 8 月 27 日创建了我的用户资料。你不会相信，但这是真的。
EOM
            )
        );

        // Turkish
        $this->assertTrue(
            $this->containsProblematicContent(
                <<<EOM
27 Ağustos 2015'te kullanıcı profilimi oluşturdum. İnanmayacaksınız ama gerçekten doğru.
EOM
            )
        );
    }

    /**
     * @test
     * @see https://discuss.flarum.org/d/31524-spam-prevention/69
     * @covers \Blomstra\Spam\Filter::allowLink
     */
    function succeeds_with_ip_allowed()
    {
        $this->assertTrue(
            $this->containsProblematicLinks(<<<EOM
Come download from http://127.0.0.1/download.html.
EOM
            ), 'Does not see local ip/download link as problematic.'
        );

        (new Filter)
            ->allowLink(fn (Uri $uri) => $uri->getHost() === '127.0.0.1');

        $this->assertFalse(
            $this->containsProblematicLinks(<<<EOM
Come download from http://127.0.0.1/download.html.
EOM
            ), 'Sees allowed local ip as problematic.'
        );
    }

    /**
     * @test
     * @covers \Blomstra\Spam\Filter::problematicWord
     * @covers \Blomstra\Spam\Filter::problematicWordsRequired
     */
    function fails_with_bad_words()
    {
        $this->assertFalse(
            $this->containsProblematicWords(<<<EOM
Feel free to test my code.
EOM
)
        );

        (new Filter)
            ->problematicWord('test')
            ->problematicWordsRequired(1);

        $this->assertTrue(
            $this->containsProblematicWords(<<<EOM
Feel free to test my code.
EOM
            )
        );
    }

    /**
     * @test
     * @see https://discuss.flarum.org/d/35060
     * @covers \Blomstra\Spam\Filter::problematicWord
     * @covers \Blomstra\Spam\Filter::problematicWordsRequired
     */
    function fails_with_many_bad_words()
    {
        $this->assertFalse(
            $this->containsProblematicWords(<<<EOM
To place order contact our telegram username: @someweirdtelegramuser Buy cannabis online in Bristol cannabis for sale online in the United Kingdom, UK Cannabis delivery, Buy weed online in UK vape edibles cannabis oil pre-rolled medical cannabis high quality marijuana for sale UK Delivery all cities in Uk London, Birmingham, Glasgow, Liverpool, Bristol, Manchester, Sheffield, Leeds, Edinburgh, Leicester, Coventry, Bradford, Cardiff, Belfast, Nottingham, Hull, Newcastle, Stoke, Southampton, Shipping to Scotland wales Northern Ireland, new castle Liverpool delivery Buy in London buy in Manchester buy in Bristol buy in Plymuth buy in Scotland buy in wales buy in stoke buy in Southampton buy in Birmingham City order high quality cannabis varieties of marijuana strains for sale online
EOM
            )
        );

        (new Filter)
            ->problematicWord(['telegram', 'cannabis', 'buy', 'order'])
            ->problematicWordsRequired(4);

        $this->assertTrue(
            $this->containsProblematicWords(<<<EOM
To place order contact our telegram username: @someweirdtelegramuser Buy cannabis online in Bristol cannabis for sale online in the United Kingdom, UK Cannabis delivery, Buy weed online in UK vape edibles cannabis oil pre-rolled medical cannabis high quality marijuana for sale UK Delivery all cities in Uk London, Birmingham, Glasgow, Liverpool, Bristol, Manchester, Sheffield, Leeds, Edinburgh, Leicester, Coventry, Bradford, Cardiff, Belfast, Nottingham, Hull, Newcastle, Stoke, Southampton, Shipping to Scotland wales Northern Ireland, new castle Liverpool delivery Buy in London buy in Manchester buy in Bristol buy in Plymuth buy in Scotland buy in wales buy in stoke buy in Southampton buy in Birmingham City order high quality cannabis varieties of marijuana strains for sale online
EOM
            )
        );
    }
}
