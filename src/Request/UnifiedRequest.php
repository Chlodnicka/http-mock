<?php
namespace InterNations\Component\HttpMock\Request;

use BadMethodCallException;
use Psr\Http\Message\RequestInterface;

class UnifiedRequest
{
    /**
     * @var RequestInterface
     */
    private $wrapped;

    /**
     * @var string
     */
    private $userAgent;

    public function __construct(RequestInterface $wrapped, array $params = [])
    {
        $this->wrapped = $wrapped;
        $this->init($params);
    }

    /**
     * Get the user agent of the request
     *
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    public function getBody()
    {
        return $this->invokeWrappedIfEntityEnclosed(__FUNCTION__, func_get_args());
    }

    public function getPostField($field)
    {
        return $this->invokeWrappedIfEntityEnclosed(__FUNCTION__, func_get_args());
    }

    public function getPostFields()
    {
        return $this->invokeWrappedIfEntityEnclosed(__FUNCTION__, func_get_args());
    }

    public function getPostFiles()
    {
        return $this->invokeWrappedIfEntityEnclosed(__FUNCTION__, func_get_args());
    }


    public function getPostFile($fieldName)
    {
        return $this->invokeWrappedIfEntityEnclosed(__FUNCTION__, func_get_args());
    }

    public function getParams()
    {
        return $this->wrapped->getBody();
    }

    public function getHeader($header)
    {
        return $this->wrapped->getHeader($header);
    }

    public function getHeaders()
    {
        return $this->wrapped->getHeaders();
    }
    public function getHeaderLines()
    {
        return $this->wrapped->getHeaders();
    }

    public function hasHeader(string $header)
    {
        return $this->wrapped->hasHeader($header);
    }

    public function getRawHeaders()
    {
        return $this->wrapped->getHeaders();
    }

    public function getQuery()
    {
        return $this->wrapped->getQuery();
    }

    public function getMethod()
    {
        return $this->wrapped->getMethod();
    }

    public function getScheme()
    {
        return $this->wrapped->getScheme();
    }

    public function getHost()
    {
        return $this->wrapped->getHost();
    }

    public function getProtocolVersion()
    {
        return $this->wrapped->getProtocolVersion();
    }

    public function getPath()
    {
        return $this->wrapped->getPath();
    }

    public function getPort()
    {
        return $this->wrapped->getPort();
    }

    /**
     * Get the username to pass in the URL if set
     *
     * @return string|null
     */
    public function getUsername()
    {
        return $this->wrapped->getUsername();
    }

    /**
     * Get the password to pass in the URL if set
     *
     * @return string|null
     */
    public function getPassword()
    {
        return $this->wrapped->getPassword();
    }

    public function getUrl()
    {
        return $this->wrapped->getUri();
    }

    public function getCookies()
    {
        return $this->wrapped->getCookies();
    }

    public function getCookie($name)
    {
        return $this->wrapped->getCookie($name);
    }

    protected function invokeWrappedIfEntityEnclosed($method, array $params = [])
    {
        if (!$this->wrapped instanceof EntityEnclosingRequestInterface) {
            throw new BadMethodCallException(
                sprintf(
                    'Cannot call method "%s" on a request that does not enclose an entity.'
                    . ' Did you expect a POST/PUT request instead of %s %s?',
                    $method,
                    $this->wrapped->getMethod(),
                    $this->wrapped->getPath()
                )
            );
        }

        return call_user_func_array([$this->wrapped, $method], $params);
    }

    private function init(array $params)
    {
        foreach ($params as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
    }
}
