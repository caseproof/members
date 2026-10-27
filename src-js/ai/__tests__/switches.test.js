import { groupAbilities, toSwitches } from '../switches';

const ability = ( name, extra = {} ) => ( {
	name,
	label: name,
	group: 'Members',
	readonly: true,
	...extra,
} );

describe( 'groupAbilities', () => {
	it( 'groups by product in first-seen order', () => {
		const groups = groupAbilities( [
			ability( 'a' ),
			ability( 'b', { group: 'Members - Admin Access' } ),
			ability( 'c' ),
		] );
		expect( groups.map( ( g ) => g.name ) ).toEqual( [
			'Members',
			'Members - Admin Access',
		] );
		expect( groups[ 0 ].abilities.map( ( a ) => a.name ) ).toEqual( [
			'a',
			'c',
		] );
	} );
} );

describe( 'toSwitches', () => {
	it( 'puts a family behind one switch', () => {
		const switches = toSwitches( [
			ability( 'list-roles', { switch: 'f/view', switchLabel: 'View' } ),
			ability( 'get-role', { switch: 'f/view', switchLabel: 'View' } ),
			ability( 'create-role', { readonly: false } ),
		] );
		expect( switches.map( ( s ) => s.key ) ).toEqual( [
			'f/view',
			'create-role',
		] );
		expect( switches[ 0 ].label ).toBe( 'View' );
		expect( switches[ 0 ].readonly ).toBe( true );
		expect( switches[ 1 ].writeOnly ).toBe( true );
	} );

	it( 'marks a family with look-ups and changes as mixed', () => {
		const [ item ] = toSwitches( [
			ability( 'get-admin-access', { switch: 'f/aa' } ),
			ability( 'update-admin-access', {
				switch: 'f/aa',
				readonly: false,
			} ),
		] );
		expect( item.mixed ).toBe( true );
		expect( item.readonly ).toBe( false );
		expect( item.writeOnly ).toBe( false );
	} );
} );
