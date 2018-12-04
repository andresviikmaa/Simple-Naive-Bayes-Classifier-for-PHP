<?php

namespace SimpleBayesClassifier\Classifier;

/**
 * Main Class
 * @package    Simple NaiveBayesClassifier for PHP
 * @subpackage    NaiveBayesClassifier
 * @author    Batista R. Harahap <batista@bango29.com>
 * @link    http://www.bango29.com
 * @license    MIT License - http://www.opensource.org/licenses/mit-license.php
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */
class NaiveBayesClassifier {

    private $store;
    private $debugMode = false;
    private $debugData = [];

    public function __construct($conf = []) {
        if (empty($conf)) {
            throw new NaiveBayesClassifierException(1001);
        }
        if (empty($conf['store'])) {
            throw new NaiveBayesClassifierException(1002);
        }
        if (empty($conf['store']['mode'])) {
            throw new NaiveBayesClassifierException(1003);
        }
        if (empty($conf['store']['db'])) {
            throw new NaiveBayesClassifierException(1004);
        }

        if (isset($conf['debug'])) {
            $this->debugMode = (bool) $conf['debug'];
        }

        switch ($conf['store']['mode']) {
            case 'redis':
                $this->store = new Store\NaiveBayesClassifierStoreRedis($conf['store']['db']);
                break;
        }
    }

    public function train($words, $set) {
        $words = $this->cleanKeywords(explode(' ', $words));
        foreach ($words as $w) {
            $this->store->trainTo(html_entity_decode($w), $set);
        }
    }

    public function deTrain($words, $set) {
        $words = trim($words);
        $words = $this->cleanKeywords(explode(' ', $words));
        foreach ($words as $w) {
            $this->store->deTrainFromSet(html_entity_decode($w), $set);
        }
    }

    /**
     * Detrain all words from needed set
     * @param string $words list of words
     * @param string $set needed set
     */
    public function deTrainAll($words, $set) {
        $words = trim($words);
        $words = $this->cleanKeywords(explode(' ', $words));
        foreach ($words as $w) {
            $this->store->deTrainAllFromSet(html_entity_decode($w), $set);
        }
    }

    public function classify($words, $count = 10, $offset = 0) {
        $P = [];
        $score = [];

        // Break keywords
        $words = trim($words);
        $keywords = $this->cleanKeywords(explode(' ', $words));

        // All sets
        $sets = $this->store->getAllSets();
        $P['sets'] = [];

        // Word counts in sets
        $setWordCounts = $this->store->getSetWordCount($sets);
        $wordCountFromSet = $this->store->getWordCountFromSet($keywords, $sets);

        if ($this->debugMode) {
            foreach ($wordCountFromSet as $keySet => $countFromSet) {
                $this->debug($keySet . ': ' . $countFromSet);
            }
        }

        foreach ($sets as $set) {
            if (empty($set)) {
                continue;
            }
            $P['sets'][$set] = 0;
            foreach ($keywords as $word) {
                // will skip value of current word if it is blacklisted
                if ($this->store->isBlacklisted($word)) {
                    continue;
                }

                $key = "{$word}{$this->store->delimiter}{$set}";
                if ($wordCountFromSet[$key] > 0) {
                    $P['sets'][$set] += $wordCountFromSet[$key] / $setWordCounts[$set];
                }
            }

            if (!is_infinite($P['sets'][$set]) && $P['sets'][$set] > 0) {
                $score[$set] = $P['sets'][$set];
            }
        }

        arsort($score);

        return array_slice($score, $offset, $count - 1);
    }

    public function blacklist($words = []) {
        $clean = [];
        if (is_string($words)) {
            $clean = [$words];
        } else {
            if (is_array($words)) {
                $clean = $words;
            }
        }
        $clean = $this->cleanKeywords($clean);

        foreach ($clean as $word) {
            $this->store->addToBlacklist($word);
        }
    }

    private function cleanKeywords($kw = []) {
        if (!empty($kw)) {
            $ret = [];
            foreach ($kw as $k) {
                $k = mb_strtolower($k);

                if (!empty($k) && mb_strlen($k) > 2) {
                    $ret[] = $k;
                }
            }
            return $ret;
        }
    }

    public function isBlacklisted($word) {
        return $this->store->isBlacklisted($word);
    }

    /**
     * Removes word from blacklist
     * @param $word
     * @return int
     */
    public function removeFromBlacklist($word) {
        return $this->store->removeFromBlacklist($word);
    }

    /**
     * Gets words count in listed sets
     * @param array $sets list of sets to get words count
     * @return array
     */
    public function getSetWordCount(array $sets) {
        return $this->store->getSetWordCount($sets);
    }

    private function debug($msg) {
        if ($this->debugMode) {
            $this->debugData[] = $msg;
        }
    }

    /**
     * Return debug data.
     * @return array
     */
    public function getDebugData(): array {
        return $this->debugData;
    }
}
