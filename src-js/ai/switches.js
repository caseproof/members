import { __ } from '@wordpress/i18n';

/**
 * Group abilities by the product they belong to (Members, then each
 * add-on), in first-seen order.
 *
 * @param {Array} abilities Abilities from the /ai payload.
 * @return {Array} Groups: { name, abilities }.
 */
export function groupAbilities( abilities ) {
	const groups = [];
	abilities.forEach( ( ability ) => {
		const name = ability.group || __( 'Other', 'members' );
		let group = groups.find( ( g ) => g.name === name );
		if ( ! group ) {
			group = { name, abilities: [] };
			groups.push( group );
		}
		group.abilities.push( ability );
	} );
	return groups;
}

/**
 * Collapse a list of abilities into switches: one per family (abilities that
 * share `switch`), in first-seen order. `readonly` when every ability only
 * reads, `writeOnly` when every one makes changes, `mixed` otherwise.
 *
 * @param {Array} abilities Abilities from the /ai payload.
 * @return {Array} Switches: { key, label, abilities, readonly, writeOnly, mixed }.
 */
export function toSwitches( abilities ) {
	const switches = [];
	abilities.forEach( ( ability ) => {
		const key = ability.switch || ability.name;
		let item = switches.find( ( s ) => s.key === key );
		if ( ! item ) {
			item = {
				key,
				label: ability.switchLabel || ability.label,
				abilities: [],
			};
			switches.push( item );
		}
		item.abilities.push( ability );
	} );
	switches.forEach( ( item ) => {
		item.readonly = item.abilities.every( ( a ) => a.readonly );
		item.writeOnly = item.abilities.every( ( a ) => ! a.readonly );
		item.mixed = ! item.readonly && ! item.writeOnly;
	} );
	return switches;
}
