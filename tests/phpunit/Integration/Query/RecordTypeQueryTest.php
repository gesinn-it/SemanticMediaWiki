<?php

namespace SMW\Tests\Integration\Query;

use SMW\Tests\MwDBaseUnitTestCase;
use SMW\Tests\Utils\UtilityFactory;

use SMW\Query\Language\SomeProperty;
use SMW\DataValueFactory;

use SMWQueryParser as QueryParser;
use SMWQuery as Query;
use SMWPrintRequest as PrintRequest;
use SMWPropertyValue as PropertyValue;
use SMWExporter as Exporter;

/**
 * @group SMW
 * @group SMWExtension
 *
 * @group semantic-mediawiki-integration
 * @group semantic-mediawiki-query
 *
 * @group semantic-mediawiki-database
 * @group medium
 *
 * @license GNU GPL v2+
 * @since 2.0
 *
 * @author mwjames
 */
class RecordTypeQueryTest extends MwDBaseUnitTestCase {

	private $queryResultValidator;
	private $fixturesProvider;

	private $semanticDataFactory;
	private $dataValueFactory;

	private $queryParser;
	private $subjects = array();

	protected function setUp() {
		parent::setUp();

		$utilityFactory = UtilityFactory::getInstance();

		$this->dataValueFactory = DataValueFactory::getInstance();
		$this->semanticDataFactory = $utilityFactory->newSemanticDataFactory();

		$this->queryResultValidator = $utilityFactory->newValidatorFactory()->newQueryResultValidator();

		$this->fixturesProvider = $utilityFactory->newFixturesFactory()->newFixturesProvider();
		$this->fixturesProvider->setupDependencies( $this->getStore() );

		$this->queryParser = new QueryParser();
	}

	protected function tearDown() {

		$fixturesCleaner = UtilityFactory::getInstance()->newFixturesFactory()->newFixturesCleaner();
		$fixturesCleaner
			->purgeSubjects( $this->subjects )
			->purgeAllKnownFacts();

		parent::tearDown();
	}

	public function testSortableRecordQuery() {

		$this->getStore()->updateData(
			$this->fixturesProvider->getFactsheet( 'Berlin' )->asEntity()
		);

		$this->getStore()->updateData(
			$this->fixturesProvider->getFactsheet( 'Paris' )->asEntity()
		);

		/**
		 * PopulationDensity is specified as `_rec`
		 *
		 * @query {{#ask: [[PopulationDensity::SomeDistinctValue]] }}
		 */
		$populationDensityValue = $this->fixturesProvider->getFactsheet( 'Berlin' )->getPopulationDensityValue();

		$description = new SomeProperty(
			$populationDensityValue->getProperty(),
			$populationDensityValue->getQueryDescription( $populationDensityValue->getWikiValue() )
		);

		$propertyValue = new PropertyValue( '__pro' );
		$propertyValue->setDataItem( $populationDensityValue->getProperty() );

		$query = new Query(
			$description,
			false,
			false
		);

		$query->querymode = Query::MODE_INSTANCES;

		$query->sortkeys = array(
			$populationDensityValue->getProperty()->getKey() => 'ASC'
		);

		$query->setLimit( 100 );

		$query->setExtraPrintouts( array(
			new PrintRequest( PrintRequest::PRINT_THIS, '' ),
			new PrintRequest( PrintRequest::PRINT_PROP, null, $propertyValue )
		) );


		$expected = array(
			$this->fixturesProvider->getFactsheet( 'Berlin' )->asSubject(),
			$this->fixturesProvider->getFactsheet( 'Berlin' )->getDemographics()->getSubject()
		);

		$this->queryResultValidator->assertThatQueryResultHasSubjects(
			$expected,
			$this->getStore()->getQueryResult( $query )
		);
	}

	/**
	 * T23926
	 */
	public function testRecordsToContainSpecialCharactersCausedByHtmlEncoding() {

		$property = $this->fixturesProvider->getProperty( 'bookrecord' );

		$semanticData = $this->semanticDataFactory
			->newEmptySemanticData( __METHOD__ );

		$this->subjects[] = $semanticData->getSubject();

		// MW parser runs htmlspecialchars on strings therefore
		// simulating it as well
		$dataValue = $this->dataValueFactory->newPropertyObjectValue(
			$property,
			htmlspecialchars( "Title with $&%'* special characters ; 2001" ),
			'',
			$semanticData->getSubject()
		);

		$semanticData->addDataValue( $dataValue	);

		$this->getStore()->updateData( $semanticData );

		/**
		 * @query "[[Book record::Title with $&%'* special characters;2001]]"
		 */
		$description = $this->queryParser
			->getQueryDescription( "[[Book record::Title with $&%'* special characters;2001]]" );

		$query = new Query(
			$description,
			false,
			true
		);

		$queryResult = $this->getStore()->getQueryResult( $query );

		$this->queryResultValidator->assertThatQueryResultHasSubjects(
			$semanticData->getSubject(),
			$queryResult
		);
	}

	/**
	 * T36019
	 */
	public function testRecordsToContainFieldComparator() {

		$property = $this->fixturesProvider->getProperty( 'bookrecord' );

		$semanticData = $this->semanticDataFactory
			->newEmptySemanticData( __METHOD__ . '-sample-2000' );

		$this->subjects['sample-2000'] = $semanticData->getSubject();

		$dataValue = $this->dataValueFactory->newPropertyObjectValue(
			$property,
			"Sample 1;2000",
			'',
			$this->subjects['sample-2000']
		);

		$semanticData->addDataValue( $dataValue	);

		$this->getStore()->updateData( $semanticData );

		$semanticData = $this->semanticDataFactory
			->newEmptySemanticData( __METHOD__ . '-sample-2001' );

		$this->subjects['sample-2001'] = $semanticData->getSubject();

		$dataValue = $this->dataValueFactory->newPropertyObjectValue(
			$property,
			"Sample 2;30 Dec 2001",
			'',
			$this->subjects['sample-2001']
		);

		$semanticData->addDataValue( $dataValue	);

		$this->getStore()->updateData( $semanticData );

		$semanticData = $this->semanticDataFactory
			->newEmptySemanticData( __METHOD__ . '-sample-1900' );

		$this->subjects['sample-1900'] = $semanticData->getSubject();

		$dataValue = $this->dataValueFactory->newPropertyObjectValue(
			$property,
			"Sample 3;1900",
			'',
			$this->subjects['sample-1900']
		);

		$semanticData->addDataValue( $dataValue	);

		$this->getStore()->updateData( $semanticData );

		$this->searchForLowerboundGreaterThanPattern();
		$this->searchForUpperboundGreaterThanPattern();
		$this->searchForLowerboundLessThanPattern();
		$this->searchForLessThanPattern();
		$this->searchForNotLikePattern();
	}

	/**
	 * @query "[[Book record::?;>1901]]"
	 */
	private function searchForLowerboundGreaterThanPattern() {
		$description = $this->queryParser
			->getQueryDescription( "[[Book record::?;>1901]]" );

		$query = new Query(
			$description,
			false,
			true
		);

		$expected = array(
			$this->subjects['sample-2000'],
			$this->subjects['sample-2001']
		);

		$this->queryResultValidator->assertThatQueryResultHasSubjects(
			$expected,
			$this->getStore()->getQueryResult( $query )
		);
	}

	/**
	 * @query "[[Book record::?;>30 Dec 2001]]"
	 */
	private function searchForUpperboundGreaterThanPattern() {
		$description = $this->queryParser
			->getQueryDescription( "[[Book record::?;>30 Dec 2001]]" );

		$query = new Query(
			$description,
			false,
			true
		);

		$this->queryResultValidator->assertThatQueryResultHasSubjects(
			$this->subjects['sample-2001'],
			$this->getStore()->getQueryResult( $query )
		);
	}

	/**
	 * @query "[[Book record::?;<1901]]"
	 */
	private function searchForLowerboundLessThanPattern() {
		$description = $this->queryParser
			->getQueryDescription( "[[Book record::?;<1901]]" );

		$query = new Query(
			$description,
			false,
			true
		);

		$this->queryResultValidator->assertThatQueryResultHasSubjects(
			$this->subjects['sample-1900'],
			$this->getStore()->getQueryResult( $query )
		);
	}

	/**
	 * @query "[[Book record::?;<30 Dec 2001]]"
	 */
	private function searchForLessThanPattern() {
		$description = $this->queryParser
			->getQueryDescription( "[[Book record::?;<30 Dec 2001]]" );

		$query = new Query(
			$description,
			false,
			true
		);

		$expected = array(
			$this->subjects['sample-1900'],
			$this->subjects['sample-2000'],
			$this->subjects['sample-2001']
		);

		$this->queryResultValidator->assertThatQueryResultHasSubjects(
			$expected,
			$this->getStore()->getQueryResult( $query )
		);
	}

	/**
	 * @query "[[Book record::?;!2000]]"
	 */
	private function searchForNotLikePattern() {
		$description = $this->queryParser
			->getQueryDescription( "[[Book record::?;!2000]]" );

		$query = new Query(
			$description,
			false,
			true
		);

		$expected = array(
			$this->subjects['sample-1900'],
			$this->subjects['sample-2001']
		);

		$this->queryResultValidator->assertThatQueryResultHasSubjects(
			$expected,
			$this->getStore()->getQueryResult( $query )
		);
	}

}
