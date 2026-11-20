import { parseBody, safeHttpUrl, youtubeId } from './post-body';

describe('parseBody', () => {
  it('keeps plain text as text', () => {
    expect(parseBody('hello <b>world</b>')).toEqual([{ kind: 'text', text: 'hello <b>world</b>' }]);
  });

  it('turns http links into links and leaves trailing punctuation outside', () => {
    expect(parseBody('See https://aviate.dev/x, ok')).toEqual([
      { kind: 'text', text: 'See ' },
      { kind: 'link', text: 'https://aviate.dev/x', href: 'https://aviate.dev/x' },
      { kind: 'text', text: ', ok' },
    ]);
  });

  it('never links javascript: or data: URLs', () => {
    const segments = parseBody('javascript:alert(1) data:text/html,hi');
    expect(segments.every((s) => s.kind === 'text')).toBe(true);
    expect(safeHttpUrl('javascript:alert(1)')).toBeNull();
  });
});

describe('youtubeId', () => {
  it('reads watch, short and youtu.be links', () => {
    expect(youtubeId('look https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10')).toBe('dQw4w9WgXcQ');
    expect(youtubeId('https://youtu.be/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
    expect(youtubeId('https://youtube.com/shorts/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
  });

  it('rejects anything that is not a clean 11-character ID', () => {
    expect(youtubeId('https://www.youtube.com/watch?v=abc"onload=alert(1)')).toBeNull();
    expect(youtubeId('https://evil.com/watch?v=dQw4w9WgXcQ')).toBeNull();
    expect(youtubeId('no links here')).toBeNull();
  });
});
