<?php

namespace Oro\Component\Config\Loader;

/**
 * An interface for strategies to match a file.
 */
interface FileMatcherInterface extends \Serializable
{
    /**
     * Decides whether the given file is matched the the rule(s) implemented by this class.
     *
     * @param \SplFileInfo $file
     *
     * @return bool
     */
    public function isMatched(\SplFileInfo $file): bool;
}
