<?php

namespace FriendlyRoute;

class Router
{
    protected $routes;
    protected $notFoundCallback;
    protected $methodNotAllowedCallback;
    protected $methodsAllowed = array();
    const ARG_PATTERN = "/({[a-zA-Z_][a-zA-Z_0-9]*})/i";
    protected $status = array(
        "FOUND" => false,
        "NOT_FOUND" => true,
        "METHOD_NOT_ALLOWED" => false,
    );
    protected $config = array(
        "projectDir" => "",
        "debug" => true
    );

    public function __construct(array $config  = array())
    {
        $this->routes = array();
        $this->notFoundCallback = null;
        $this->methodNotAllowedCallback = null;
        $this->setConfig($config);
    }

    // ------------------------------------------------------------------------------------------------
    // SET ROUTER CONFIGURATIONS
    private function setConfig(array $config)
    {
        if (isset($config["projectDir"])) :
            $this->config["projectDir"] = "/" . $config["projectDir"];
            $this->config["projectDir"] = preg_replace("/\/\//", "/", $this->config["projectDir"]);
            if ($this->config["projectDir"] == "/") :
                $this->config["projectDir"] = "";
            endif;
        endif;
        if (isset($config["debug"])) :
            $this->config["debug"] = $config["debug"];
        endif;
    }

    // ------------------------------------------------------------------------------------------------
    // GET REQUEST URI ARGS
    private function getRequestUriArgs(string $urlPattern, string $requestUri): array
    {
        preg_match_all(self::ARG_PATTERN, $urlPattern, $argsKeys);
        $argsKeys = $argsKeys[0];

        $urlPatterSplited = preg_split(self::ARG_PATTERN, $urlPattern);
        $argValues = $requestUri;;

        foreach ($urlPatterSplited as $value) :
            if (!empty($value)) :
                $value = preg_replace("/\//i", "(\/){1}", $value);
                $argValues = preg_replace("/{$value}/", "/", $argValues);
            endif;
        endforeach;
        $argValues = preg_replace("/^\//", "", $argValues);
        $argValues = explode("/", $argValues);

        $args = array();
        if (count($argsKeys) == count($argsKeys)) :
            foreach ($argsKeys as $key => $argsKey) :
                $argsKey = preg_replace("/{|}/", "", $argsKey);
                $args[$argsKey] = $argValues[$key];
            endforeach;
        endif;
        return $args;
    }

    // ------------------------------------------------------------------------------------------------
    // GET REQUEST URI
    private function getRequestUri(): string
    {
        return parse_url(preg_replace("#^" . $this->config["projectDir"] . "#", "", $_SERVER["REQUEST_URI"], 1), PHP_URL_PATH);
    }

    // ------------------------------------------------------------------------------------------------
    // VERIFY IF IS VALID REQUEST URI
    private function isValidRequestUri(string $urlPattern, string $method): bool
    {
        if ($this->matchUrlPattern($urlPattern) && !$this->status["FOUND"]) :
            if (strtoupper($method) == $_SERVER["REQUEST_METHOD"]) :
                $this->status["FOUND"] = true;
                $this->status["NOT_FOUND"] = false;
                return true;
            endif;
        endif;
        return false;
    }

    // ------------------------------------------------------------------------------------------------
    // CHECK REQUEST
    public function checkRequest(string $urlPattern, string $method): bool
    {
        if ($this->matchUrlPattern($urlPattern) && !$this->status["FOUND"]) :
            if (strtoupper($method) == $_SERVER["REQUEST_METHOD"]) :
                return true;
            endif;
        endif;
        return false;
    }

    // ------------------------------------------------------------------------------------------------
    // MATCH URL PATTERN
    private function matchUrlPattern(string $urlPattern): bool
    {
        $urlPattern = strtolower($urlPattern);
        $requestUri  = $this->getRequestUri();

        $replacePatterns = array(
            self::ARG_PATTERN,
            "/\//i"
        );
        $replacement = array(
            "(.)+",
            "(\/){1}"
        );

        $validRequestUriPattern  = "/^" . preg_replace($replacePatterns, $replacement, $urlPattern) . "$/i";
        return preg_match($validRequestUriPattern, $requestUri) && (substr_count($urlPattern, "/") == substr_count($requestUri, "/"));
    }

    // ------------------------------------------------------------------------------------------------
    // SET ROUTE
    private function setRoute(string $urlPattern, string $method)
    {
        $this->routes[] = array(
            "method" => strtoupper($method),
            "urlPattern" => $urlPattern
        );
    }

    // ------------------------------------------------------------------------------------------------
    // CALLBACK REQUEST NOT FOUND
    public function notFound(callable|null $callback)
    {
        if ($this->status["NOT_FOUND"] && is_callable($this->notFoundCallback) && (!$this->status["METHOD_NOT_ALLOWED"] || ($this->status["METHOD_NOT_ALLOWED"] && !is_callable($this->methodNotAllowedCallback)))) :
            http_response_code(404);
            $callback();
        elseif ($this->config["debug"] && $this->status["NOT_FOUND"] && !is_callable($callback) && (!$this->status["METHOD_NOT_ALLOWED"])) :
            http_response_code(404);
            echo "DEBUG: NOT FOUND";
        endif;
        $this->notFoundCallback = $callback;
    }

    // ------------------------------------------------------------------------------------------------
    // CALLBACK REQUEST METHOD NOT ALLOWED
    public function methodNotAllowed(callable|null $callback)
    {
        if ($this->status["METHOD_NOT_ALLOWED"] && is_callable($this->methodNotAllowedCallback)) :
            $methodsAllowed = $this->getMethodsAllowed();
            $callback($methodsAllowed);
        elseif ($this->config["debug"] && $this->status["METHOD_NOT_ALLOWED"] && !is_callable($this->methodNotAllowedCallback)) :
            http_response_code(405);
            echo "DEBUG: METHOD NOT ALLOWED<br>";
            echo "ALLOWED METHOD<br>";
            print_r($this->getMethodsAllowed());
        endif;
        $this->methodNotAllowedCallback = $callback;
    }

    // ------------------------------------------------------------------------------------------------
    // SET METHOD ALLOWED
    private function setMethodAllowed()
    {
        if (!is_object($this->routes) and !is_array($this->routes)) :
            $this->routes = array();
        endif;
        if ($this->status["NOT_FOUND"]) :
            foreach ($this->routes as $route) :
                if ($this->matchUrlPattern($route["urlPattern"])) :
                    http_response_code(405);
                    $this->status["METHOD_NOT_ALLOWED"] =  true;
                    $this->methodsAllowed[] = $route["method"];
                endif;
            endforeach;
        endif;
    }

    // ------------------------------------------------------------------------------------------------
    // GET METHOD ALLOWED
    private function getMethodsAllowed(): array
    {
        return $this->methodsAllowed;
    }

    // ------------------------------------------------------------------------------------------------
    // GET REQUEST
    public function get(string | array $urlPattern, callable|null $callback)
    {
        $this->setRequest("GET", $urlPattern, $callback);
    }

    // ------------------------------------------------------------------------------------------------
    // POST REQUEST
    public function post(string | array $urlPattern, callable|null $callback)
    {
        $this->setRequest("POST", $urlPattern, $callback);
    }

    // ------------------------------------------------------------------------------------------------
    // PUT REQUEST
    public function put(string | array $urlPattern, callable|null $callback)
    {
        $this->setRequest("PUT", $urlPattern, $callback);
    }

    // ------------------------------------------------------------------------------------------------
    // DELETE REQUEST
    public function delete(string | array $urlPattern, callable|null $callback)
    {
        $this->setRequest("DELETE", $urlPattern, $callback);
    }

    // ------------------------------------------------------------------------------------------------
    // SET REQUEST METHOD
    public function set(string|array $method, string $urlPattern, callable|null $callback)
    {
        if (is_array($method)) :
            foreach ($method as $value) :
                $this->setRequest($value, $urlPattern, $callback);
            endforeach;
        else :
            $this->setRequest($method, $urlPattern, $callback);
        endif;
    }

    // ------------------------------------------------------------------------------------------------
    // SET REQUEST
    private function setRequest(string $method, string | array $urlPatterns, callable|null $callback)
    {
        if (is_string($urlPatterns)) :
            $urlPatterns = array($urlPatterns);
        endif;

        foreach ($urlPatterns as $key => $urlPattern) :
            $this->setRoute($urlPattern, strtoupper($method));

            if ($this->isValidRequestUri($urlPattern, strtoupper($method))) :
                $requestUri = $this->getRequestUri();
                $args = $this->getRequestUriArgs($urlPattern, $requestUri);
                $args["urlIndex"] = $key;
                $urlIndex =  $key;
                $callback($args, $urlIndex);
            endif;
        endforeach;
    }

    // ------------------------------------------------------------------------------------------------
    // RUN ROUTER
    public function run()
    {
        $this->setMethodAllowed();
        $this->methodNotAllowed($this->methodNotAllowedCallback);
        $this->notFound($this->notFoundCallback);
    }
}
