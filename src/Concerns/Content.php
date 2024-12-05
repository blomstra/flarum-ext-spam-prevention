<?php

namespace Blomstra\Spam\Concerns;

use Flarum\Locale\LocaleManager;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Uri;
use LanguageDetection\Language;
use Blomstra\Spam\Filter;

trait Content
{
    public function containsProblematicContent(string $content, User $actor = null): bool
    {
        return $this->containsProblematicLinks($content, $actor)
            || $this->containsAlternateLanguage($content)
            || $this->containsProblematicWords($content);
    }

    public function containsProblematicLinks(string $content, User $actor = null): bool
    {
        $domains = Filter::getAcceptableDomains();

        // First check for links.
        preg_match_all('~(?<uri>(\w+)://(?<domain>[-\w.]+))~', $content, $links);

        foreach (array_filter($links['domain']) as $link) {
            $uri = (new Uri("http://$link"));
            $host = $uri->getHost();

            // If custom callable allows it.
            foreach (Filter::$allowLinksCallables as $callable) {
                if ($callable($uri, $actor) === true) continue 2;
            }

            // Match exact domain or subdomains.
            if (Arr::first($domains, fn ($domain) => $domain === $host || Str::endsWith($host, ".$domain"))) {
                continue;
            }

            return true;
        }

        return
            // phone
            preg_match('/(\+|00)([𝟘𝟙𝟚𝟛𝟜𝟝𝟞𝟟𝟠𝟡0-9-\p{No}\p{Nd}\p{M}]){9,}/u', $content) ||
            // email
            preg_match('~\S+@\S+\.\S+~', $content);
    }

    public function containsAlternateLanguage(string $content): bool
    {
        // strip links
        $content = preg_replace('~[\S]+@[\S]+\.[\S]+~', '', $content);
        $content = preg_replace('~https?:\/\/([-\w.]+)~', '', $content);
        $content = preg_replace('~(\+|00)[0-9 ]{9,}~', '', $content);

        // Let's not do language analysis on short strings.
        if (mb_strlen($content) < 10) return false;

        /** @var LocaleManager $locales */
        $locales = resolve(LocaleManager::class);

        $locales = array_keys($locales->getLocales());

        $languageDetection = (new Language)->detect($content);

        return ! empty($languageDetection) && ! in_array((string) $languageDetection, $locales);
    }

    public function containsProblematicWords(string $content): bool
    {
        $words = array_unique(Filter::$problematicWords);

        if (empty($words)) {
            return false;
        }

        $required = Filter::$problematicWordsRequired;

        foreach ($words as $word) {
            if (str_contains($content, $word)) {
                $required--;
            }

            if ($required === 0) {
                return true;
            }
        }

        return false;
    }
}
