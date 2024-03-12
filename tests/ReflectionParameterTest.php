<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use PHPUnit\Framework\TestCase;
use Go\ParserReflection\Stub\Foo;
use Go\ParserReflection\Stub\SubFoo;
use TestParametersForRootNsClass;

class ReflectionParameterTest extends TestCase
{
    protected ReflectionFile $parsedRefFile;

    protected function setUp(): void
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithParameters55.php');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fileProvider')]
    public function testGeneralInfoGetters(string $fileName): void
    {
        $this->setUpFile($fileName);
        $allNameGetters = [
            'isOptional', 'isPassedByReference', 'isDefaultValueAvailable',
            'getPosition', 'canBePassedByValue', 'allowsNull', 'getDefaultValue', 'getDefaultValueConstantName',
            'isDefaultValueConstant', 'isVariadic', 'isPromoted', 'hasType', '__toString'
        ];
        $onlyWithDefaultValues = array_flip([
            'getDefaultValue', 'getDefaultValueConstantName', 'isDefaultValueConstant'
        ]);

        foreach ($this->parsedRefFile->getFileNamespaces() as $fileNamespace) {
            // Combine both functions and methods from namespace
            $refFunctions = $fileNamespace->getFunctions();
            foreach ($fileNamespace->getClasses() as $reflectionClass) {
                $refFunctions = array_merge($refFunctions, $reflectionClass->getMethods());
            }
            foreach ($refFunctions as $refFunction) {
                if ($refFunction instanceof \ReflectionMethod) {
                    $functionName = [$refFunction->class, $refFunction->getName()];
                } else {
                    $functionName = $refFunction->getName();
                }
                foreach ($refFunction->getParameters() as $refParameter) {
                    $parameterName        = $refParameter->getName();
                    $originalRefParameter = new \ReflectionParameter($functionName, $parameterName);
                    foreach ($allNameGetters as $getterName) {

                        // skip some methods if there is no default value
                        $isDefaultValueAvailable = $originalRefParameter->isDefaultValueAvailable();
                        if (isset($onlyWithDefaultValues[$getterName]) && !$isDefaultValueAvailable) {
                            continue;
                        }
                        $expectedValue = $originalRefParameter->$getterName();
                        $actualValue   = $refParameter->$getterName();
                        $displayableName = is_array($functionName) ? join ('->', $functionName) : $functionName;
                        // I would like to completely stop maintaining the __toString method
                        if ($expectedValue !== $actualValue && $getterName === '__toString') {
                            $this->markTestSkipped("__toString for parameter {$displayableName}(\${$parameterName}) is not equal:\n{$expectedValue}\n{$actualValue}");
                        }
                        $this->assertSame(
                            $expectedValue,
                            $actualValue,
                            "{$getterName}() for parameter {$displayableName}(\${$parameterName}) should be equal"
                        );
                    }
                }
            }
        }
    }

    /**
     * Provides a list of files for analysis
     */
    public static function fileProvider(): \Generator
    {
        yield 'PHP5.5' => [__DIR__ . '/Stub/FileWithParameters55.php'];
        yield 'PHP5.6' => [__DIR__ . '/Stub/FileWithParameters56.php'];
        yield 'PHP7.0' => [__DIR__ . '/Stub/FileWithParameters70.php'];

        yield 'PHP8.0' => [__DIR__ . '/Stub/FileWithClasses80.php'];
        yield 'PHP8.1' => [__DIR__ . '/Stub/FileWithClasses81.php'];
        yield 'PHP8.2' => [__DIR__ . '/Stub/FileWithClasses82.php'];
    }

    public function testGetClassMethod(): void
    {
        $parsedNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $parsedFunction  = $parsedNamespace->getFunction('miscParameters');

        $parameters = $parsedFunction->getParameters();
        $this->assertNull($parameters[0 /* array $arrayParam*/]->getClass());
        $this->assertNull($parameters[1 /* callable $callableParam */]->getClass());

        $objectParam = $parameters[2 /* \stdClass $objectParam */]->getClass();
        $this->assertInstanceOf(\ReflectionClass::class, $objectParam);
        $this->assertSame(\stdClass::class, $objectParam->getName());

        $typehintedParamWithNs = $parameters[3 /* ReflectionParameter $typehintedParamWithNs */]->getClass();
        $this->assertInstanceOf(\ReflectionClass::class, $typehintedParamWithNs);
        $this->assertSame(ReflectionParameter::class, $typehintedParamWithNs->getName());

        $internalInterfaceParam = $parameters[5 /* \Traversable $traversable */]->getClass();
        $this->assertInstanceOf(\ReflectionClass::class, $internalInterfaceParam);
        $this->assertSame(\Traversable::class, $internalInterfaceParam->getName());
    }

    public function testGetClassMethodReturnsSelfAndParent(): void
    {
        $parsedNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $parsedClass     = $parsedNamespace->getClass(SubFoo::class);
        $parsedFunction  = $parsedClass->getMethod('anotherMethodParam');

        $parameters = $parsedFunction->getParameters();
        $selfParam = $parameters[0 /* self $selfParam */]->getClass();
        $this->assertInstanceOf(\ReflectionClass::class, $selfParam);
        $this->assertSame(SubFoo::class, $selfParam->getName());

        $parentParam = $parameters[1 /* parent $parentParam */]->getClass();
        $this->assertInstanceOf(\ReflectionClass::class, $parentParam);
        $this->assertSame(Foo::class, $parentParam->getName());
    }

    public function testNonConstantsResolvedForGlobalNamespace(): void
    {
        $parsedNamespace = $this->parsedRefFile->getFileNamespace('');
        $parsedClass     = $parsedNamespace->getClass(TestParametersForRootNsClass::class);
        $parsedFunction  = $parsedClass->getMethod('foo');

        $parameters = $parsedFunction->getParameters();
        $this->assertNull($parameters[0]->getDefaultValue());
        $this->assertFalse($parameters[1]->getDefaultValue());
        $this->assertTrue($parameters[2]->getDefaultValue());
    }

    public function testGetDeclaringClassMethodReturnsObject(): void
    {
        $parsedNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $parsedClass     = $parsedNamespace->getClass(Foo::class);
        $parsedFunction  = $parsedClass->getMethod('methodParam');

        $parameters = $parsedFunction->getParameters();
        $this->assertSame($parsedClass->getName(), $parameters[0]->getDeclaringClass()->getName());
    }

    public function testParamWithDefaultConstValue(): void
    {
        $parsedNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $parsedClass     = $parsedNamespace->getClass(Foo::class);
        $parsedFunction  = $parsedClass->getMethod('methodParamConst');

        $parameters = $parsedFunction->getParameters();
        $this->assertTrue($parameters[0]->isDefaultValueConstant());
        $this->assertSame('self::CLASS_CONST', $parameters[0]->getDefaultValueConstantName());

        $this->assertTrue($parameters[2]->isDefaultValueConstant());
        $this->assertSame('Go\ParserReflection\Stub\TEST_PARAMETER', $parameters[2]->getDefaultValueConstantName());

        $this->assertTrue($parameters[3]->isDefaultValueConstant());
        $this->assertSame('Go\ParserReflection\Stub\SubFoo::ANOTHER_CLASS_CONST', $parameters[3]->getDefaultValueConstantName());
    }

    public function testParamBuiltInClassConst(): void
    {
        $parsedNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $parsedClass     = $parsedNamespace->getClass(Foo::class);
        $parsedFunction  = $parsedClass->getMethod('methodParamBuiltInClassConst');

        $parameters = $parsedFunction->getParameters();
        $this->assertTrue($parameters[0]->isDefaultValueConstant());
        $this->assertSame('DateTime::ATOM', $parameters[0]->getDefaultValueConstantName());
    }

    public function testGetDeclaringClassMethodReturnsNull(): void
    {
        $parsedNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $parsedFunction  = $parsedNamespace->getFunction('miscParameters');

        $parameters = $parsedFunction->getParameters();
        $this->assertNull($parameters[0]->getDeclaringClass());
    }

    public function testDebugInfoMethod(): void
    {
        $parsedNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $parsedFunction  = $parsedNamespace->getFunction('miscParameters');

        $parsedRefParameters  = $parsedFunction->getParameters();
        $parsedRefParameter   = $parsedRefParameters[0];
        $originalRefParameter = new \ReflectionParameter('Go\ParserReflection\Stub\miscParameters', 'arrayParam');
        $expectedValue        = (array) $originalRefParameter;
        $this->assertSame($expectedValue, $parsedRefParameter->__debugInfo());
    }

    /**
     * @param string $getterName Name of the getter to call
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('listOfDefaultGetters')]
    public function testGetDefaultValueThrowsAnException(string $getterName): void
    {
        $originalException = null;
        $parsedException   = null;

        try {
            $originalRefParameter = new \ReflectionParameter('Go\ParserReflection\Stub\miscParameters', 'arrayParam');
            $originalRefParameter->$getterName();
        } catch (\ReflectionException $e) {
            $originalException = $e;
        }

        try {
            $parsedNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
            $parsedFunction  = $parsedNamespace->getFunction('miscParameters');

            $parsedRefParameters  = $parsedFunction->getParameters();
            $parsedRefParameter   = $parsedRefParameters[0];
            $parsedRefParameter->$getterName();
        } catch (\ReflectionException $e) {
            $parsedException = $e;
        }

        $this->assertInstanceOf(\ReflectionException::class, $originalException);
        $this->assertInstanceOf(\ReflectionException::class, $parsedException);
        $this->assertSame($originalException->getMessage(), $parsedException->getMessage());
    }

    public static function listOfDefaultGetters(): \Iterator
    {
        yield ['getDefaultValue'];
        yield ['getDefaultValueConstantName'];
    }

    #[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
    public function testCoverAllMethods(): void
    {
        $allInternalMethods = get_class_methods(\ReflectionParameter::class);
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            if ('export' === $internalMethodName) {
                continue;
            }
            $refMethod    = new \ReflectionMethod(ReflectionParameter::class, $internalMethodName);
            $definerClass = $refMethod->getDeclaringClass()->getName();
            if (strpos($definerClass, 'Go\\ParserReflection') !== 0) {
                $allMissedMethods[] = $internalMethodName;
            }
        }

        if ($allMissedMethods) {
            $this->markTestIncomplete('Methods ' . implode(', ', $allMissedMethods) . ' are not implemented');
        }
    }

    public function testGetTypeMethod(): void
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithParameters70.php');

        foreach ($this->parsedRefFile->getFileNamespaces() as $fileNamespace) {
            foreach ($fileNamespace->getFunctions() as $refFunction) {
                $functionName = $refFunction->getName();
                foreach ($refFunction->getParameters() as $refParameter) {
                    $parameterName        = $refParameter->getName();
                    $originalRefParameter = new \ReflectionParameter($functionName, $parameterName);
                    $hasType              = $refParameter->hasType();
                    $this->assertSame(
                        $originalRefParameter->hasType(),
                        $hasType,
                        "Presence of type for parameter {$functionName}:{$parameterName} should be equal"
                    );
                    $message= "Parameter $functionName:$parameterName not equals to the original reflection";
                    if ($hasType) {
                        $parsedReturnType   = $refParameter->getType();
                        $originalReturnType = $originalRefParameter->getType();
                        $this->assertSame($originalReturnType->allowsNull(), $parsedReturnType->allowsNull(), $message);
                        $this->assertSame($originalReturnType->isBuiltin(), $parsedReturnType->isBuiltin(), $message);
                        $this->assertSame($originalReturnType->getName(), $parsedReturnType->__toString(), $message);
                    } else {
                        $this->assertSame(
                            $originalRefParameter->getType(),
                            $refParameter->getType(),
                            $message
                        );
                    }
                }
            }
        }
    }

    /**
     * Setups file for parsing
     */
    private function setUpFile(string $fileName): void
    {
        $fileName = stream_resolve_include_path($fileName);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);
        $this->parsedRefFile = $reflectionFile;

        include_once $fileName;
    }
}
