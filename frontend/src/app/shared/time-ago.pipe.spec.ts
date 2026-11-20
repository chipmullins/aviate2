import { TimeAgoPipe } from './time-ago.pipe';

describe('TimeAgoPipe', () => {
  const pipe = new TimeAgoPipe();
  const ago = (ms: number) => new Date(Date.now() - ms).toISOString();

  it('formats recent times compactly', () => {
    expect(pipe.transform(ago(5_000))).toBe('just now');
    expect(pipe.transform(ago(5 * 60_000))).toBe('5m');
    expect(pipe.transform(ago(3 * 3_600_000))).toBe('3h');
    expect(pipe.transform(ago(2 * 86_400_000))).toBe('2d');
  });

  it('handles missing values', () => {
    expect(pipe.transform(null)).toBe('');
  });
});
