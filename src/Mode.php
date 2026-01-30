<?php

namespace NIH\Container;

enum Mode
{
    case Default;
    case Instance;
    case Ghost;
    case NestedGhost;
    case Proxy;
    case NestedProxy;
}
