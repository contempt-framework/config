<?php

declare(strict_types=1);

namespace Contempt\Config\Exception;

use Contempt\Core\Exception\ContemptException;

final class InvalidConfigurationPath extends \InvalidArgumentException implements ContemptException {}
