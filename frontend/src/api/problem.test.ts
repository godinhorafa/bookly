import { ProblemError } from './problem';

describe('ProblemError', () => {
  it('lists every broken booking rule', () => {
    const error = new ProblemError(422, {
      type: 'https://github.com/godinhorafa/bookly/blob/main/docs/api/problems.md#booking-rules-violated',
      detail: 'The booking request breaks one or more rules.',
      violations: ['Too soon.', 'Off the grid.'],
    });

    expect(error.kind).toBe('booking-rules-violated');
    expect(error.messages()).toEqual(['Too soon.', 'Off the grid.']);
  });

  it('falls back to field errors, then to the detail', () => {
    expect(new ProblemError(422, { errors: { start: ['The start field is required.'] } }).messages()).toEqual(['The start field is required.']);
    expect(new ProblemError(409, { detail: 'Slot taken.' }).messages()).toEqual(['Slot taken.']);
    expect(new ProblemError(500, undefined).messages()).toEqual(['Request failed with status 500']);
  });
});
