Spam prevention is a tool (not an extension one can enable from the admin area just yet) based on our spam fighting experience on discuss. By adding just one line of code to your `extend.php` a whole set of smart content analysis tooling is activated.

### Requirements

- flarum/approval - this is required
- flarum/flags - this is recommended
- fof/spamblock - optional, when enabled immediately deletes users that open discussions with spam subjects

### Installation

```
composer require blomstra/spam-prevention
```

Now enable this extension with bare minimum functionality by editing the `extend.php` in the root installation directory of flarum, next to your `flarum` and `composer.json` files:

```php
<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Extend;
use Blomstra\Spam;

return [
    // Register extenders here to customize your forum!
   (new Spam\Filter)->enable(),
];
```

That's it. Read below what this package does and how to customize its behavior.

### Spam prevent logic

How does it try to identify spam?

- it scans for phone numbers
- it scans for email addresses
- it scans for links that aren't in the allow list
- it identifies the language used against the language packs installed

Where does it scan for spam?

- it scans (first) posts
- it scans user bio's

How does it take action?

- it flags posts with flarum/flags enabled
- it marks posts as not approved with flarum/approval enabled
- it overwrites user bio's if it identifies spam
- it marks users as spammer if a discussion subject contains spam with fof/spamblock

### Customization

```php
<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Extend;
use Blomstra\Spam;

return [
    (new Spam\Filter)
        // use domain name
        ->allowLinksFromDomain('luceos.com')
        // or just a full domain with protocol, only the host name is used
        ->allowLinksFromDomain('http://flarum.org')
        // even a link works, only the domain will be used
        ->allowLinksFromDomain('discuss.flarum.org/d/26095')
        // Alternatively, use an array of domains
        ->allowLinksFromDomains([
            'luceos.com',
            'flarum.org',
            'discuss.flarum.org'
        ])
        // How long after sign up all posts are scrutinized for bad content
        ->checkForUserUpToHoursSinceSignUp(5)
        // How many of the first posts of a user to scrutinize for bad content
        ->checkForUserUpToPostContribution(5)
        // Specify the user Id of the moderator raising flags for some actions, otherwise the first admin is used
        ->moderateAsUser(2)
        ->enable(),
];
```

### FAQ

__Why is there no admin settings page?__

Building a ux for an extension takes a lot of time, especially when the code isn't mature enough. Changes to the inner workings might affect a settings page countless times. We will build a settings page once we're satisfied about the features contained in this tool.

---

- Blomstra provides managed Flarum hosting and development.
- https://blomstra.net
- https://github.com/blomstra/flarum-ext-spam-prevention
